<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Corrige event_start_datetime / event_end_datetime de las fichas logísticas
 * que quedaron corridas por el bug de zona horaria: la API las devolvía en
 * UTC, el front las mostraba +3hs y al guardar la ficha se volvían a
 * persistir así, sumando 3hs por cada guardado.
 *
 * No hay registro de cuántas veces se guardó cada ficha, así que el comando
 * por defecto solo LISTA (dry-run) y sugiere una corrección:
 * - event_start_datetime: si restando 3h·k da exactamente la fecha/hora del
 *   presupuesto (el valor con el que se precarga), esa es la corrección
 *   (segura).
 * - event_end_datetime: no hay forma segura de saberlo; se muestran las
 *   opciones -3h/-6h/-9h y se corrige a mano por ficha (--id) o, si se sabe
 *   que todas se corrieron lo mismo, en bloque con --end-shifts.
 *
 * Uso:
 *   php artisan logistics-sheet:fix-timezone                    # listar
 *   php artisan logistics-sheet:fix-timezone --apply-suggested  # aplicar inicios seguros
 *   php artisan logistics-sheet:fix-timezone --apply-suggested --end-shifts=1  # + restar 3hs a todos los fines
 *   php artisan logistics-sheet:fix-timezone --id=12 --field=end --shifts=2 --apply
 */
class FixLogisticsSheetTimezone extends Command
{
    protected $signature = 'logistics-sheet:fix-timezone
        {--id= : ID de la ficha logística a corregir a mano (con --field y --shifts)}
        {--field=end : start | end (con --id)}
        {--shifts=1 : Cantidad de veces que se restan 3hs (con --id)}
        {--apply : Aplica la corrección manual de --id}
        {--apply-suggested : Aplica las correcciones seguras de inicio a todas las fichas listadas}
        {--end-shifts=0 : Con --apply-suggested, resta 3hs esta cantidad de veces a TODOS los fines listados}
        {--max-shifts=3 : Máximo de corrimientos a considerar en la sugerencia}
        {--include-past : Incluye fichas de eventos que ya pasaron}';

    protected $description = 'Corrige event_start_datetime / event_end_datetime de las fichas logísticas corridas +3hs por el bug de zona horaria';

    private const SHIFT_HOURS = 3;

    public function handle()
    {
        if ($this->option('id')) {
            return $this->fixOne();
        }

        $maxShifts = max((int) $this->option('max-shifts'), 1);
        $endShifts = max((int) $this->option('end-shifts'), 0);

        $query = DB::table('logistics_sheets as ls')
            ->join('budgets as b', 'b.id', '=', 'ls.id_budget')
            ->where(function ($q) {
                $q->whereNotNull('ls.event_start_datetime')->orWhereNotNull('ls.event_end_datetime');
            })
            ->select('ls.id', 'ls.id_budget', 'ls.event_start_datetime', 'ls.event_end_datetime', 'b.date_event', 'b.time_event')
            ->orderBy('b.date_event');

        if (!$this->option('include-past')) {
            $query->whereDate('b.date_event', '>=', now()->toDateString());
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->info('No hay fichas con fecha de inicio/fin cargada para revisar.');
            return 0;
        }

        $table = [];
        $updates = [];

        foreach ($rows as $row) {
            $budgetStart = $row->date_event
                ? Carbon::parse(substr($row->date_event, 0, 10) . ' ' . ($row->time_event ?: '00:00:00'))
                : null;

            $newStart = null;
            $startNote = '-';
            if ($row->event_start_datetime) {
                [$newStart, $startNote] = $this->suggestStart(Carbon::parse($row->event_start_datetime), $budgetStart, $maxShifts);
            }

            $newEnd = null;
            $endNote = '-';
            if ($row->event_end_datetime && $endShifts > 0) {
                $newEnd = Carbon::parse($row->event_end_datetime)->subHours($endShifts * self::SHIFT_HOURS);
                $endNote = $newEnd->format('Y-m-d H:i') . " (-{$endShifts}x3h)";
            } elseif ($row->event_end_datetime) {
                $endNote = 'revisar (ver opciones abajo)';
            }

            $table[] = [
                $row->id,
                $row->id_budget,
                $budgetStart ? $budgetStart->format('Y-m-d H:i') : '-',
                $row->event_start_datetime ? substr($row->event_start_datetime, 0, 16) : '-',
                $startNote,
                $row->event_end_datetime ? substr($row->event_end_datetime, 0, 16) : '-',
                $endNote,
            ];

            $changes = [];
            if ($newStart) {
                $changes['event_start_datetime'] = $newStart->format('Y-m-d H:i:s');
            }
            if ($newEnd) {
                $changes['event_end_datetime'] = $newEnd->format('Y-m-d H:i:s');
            }
            if ($changes) {
                $updates[$row->id] = $changes;
            }
        }

        $this->table(
            ['Ficha', 'Presupuesto', 'Inicio presupuesto', 'Inicio ficha', 'Inicio sugerido', 'Fin ficha', 'Fin corregido'],
            $table
        );

        $this->line('Opciones de fin (-3h / -6h / -9h) para revisar a mano:');
        foreach ($rows as $row) {
            if (!$row->event_end_datetime) {
                continue;
            }
            $end = Carbon::parse($row->event_end_datetime);
            $options = collect(range(1, $maxShifts))
                ->map(fn ($k) => '-' . ($k * self::SHIFT_HOURS) . 'h: ' . $end->copy()->subHours($k * self::SHIFT_HOURS)->format('Y-m-d H:i'))
                ->implode(' | ');
            $this->line("  Ficha {$row->id}: {$options}");
        }

        if (!$this->option('apply-suggested')) {
            $this->newLine();
            $this->warn('Dry-run: no se modificó nada. Revisá la tabla y corré con --apply-suggested, o corregí una ficha puntual con --id=<id> --field=end --shifts=<n> --apply.');
            return 0;
        }

        if (empty($updates)) {
            $this->info('No hay correcciones sugeridas para aplicar.');
            return 0;
        }

        if (!$this->confirm('Se van a actualizar ' . count($updates) . ' ficha(s) con las correcciones sugeridas. ¿Continuar?')) {
            return 0;
        }

        DB::transaction(function () use ($updates) {
            foreach ($updates as $id => $changes) {
                DB::table('logistics_sheets')->where('id', $id)->update($changes);
            }
        });

        $this->info('Listo: ' . count($updates) . ' ficha(s) corregidas.');
        return 0;
    }

    /**
     * @return array{0: ?Carbon, 1: string}
     */
    private function suggestStart(Carbon $start, ?Carbon $budgetStart, int $maxShifts): array
    {
        if (!$budgetStart) {
            return [null, 'sin fecha de presupuesto'];
        }
        if ($start->equalTo($budgetStart)) {
            return [null, 'OK'];
        }
        foreach (range(1, $maxShifts) as $k) {
            $candidate = $start->copy()->subHours($k * self::SHIFT_HOURS);
            if ($candidate->equalTo($budgetStart)) {
                return [$candidate, $candidate->format('Y-m-d H:i') . " (-{$k}x3h, seguro)"];
            }
        }
        return [null, 'editado por el cliente, revisar'];
    }

    private function fixOne(): int
    {
        $id = (int) $this->option('id');
        $field = $this->option('field') === 'start' ? 'event_start_datetime' : 'event_end_datetime';
        $shifts = max((int) $this->option('shifts'), 1);

        $current = DB::table('logistics_sheets')->where('id', $id)->value($field);
        if (!$current) {
            $this->error("La ficha {$id} no existe o no tiene {$field} cargado.");
            return 1;
        }

        $new = Carbon::parse($current)->subHours($shifts * self::SHIFT_HOURS)->format('Y-m-d H:i:s');
        $this->line("Ficha {$id} · {$field}: {$current} → {$new}");

        if (!$this->option('apply')) {
            $this->warn('Dry-run: agregá --apply para guardar el cambio.');
            return 0;
        }

        DB::table('logistics_sheets')->where('id', $id)->update([$field => $new]);
        $this->info('Guardado.');
        return 0;
    }
}
