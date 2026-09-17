<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Mail\LogisticsSheetReminder;
use App\Mail\LogisticsSheetRequest;
use App\Models\Budget;
use App\Models\LogisticsSheet;
use App\Services\MailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogisticsSheetController extends Controller
{
    /**
     * Obtiene la ficha logística de un presupuesto (la crea, con un token
     * nuevo, si todavía no existe) y le envía por mail al cliente el link
     * público para que la complete. Llamarlo de nuevo más adelante reenvía
     * el mismo link (sirve como "reenvío" del reclamo).
     */
    public function getOrCreate(Request $request, $idBudget)
    {
        try {
            $budget = Budget::find($idBudget);

            if (!$budget) {
                return ApiResponse::create('Presupuesto no encontrado', 404, ['error' => 'Presupuesto no encontrado'], [
                    'request' => $request,
                    'module' => 'logistics sheet',
                    'endpoint' => 'Obtener o crear ficha logística',
                ]);
            }

            if (!$budget->client_mail) {
                return ApiResponse::create('El presupuesto no tiene un email de cliente cargado', 422, ['error' => 'El presupuesto no tiene un email de cliente cargado'], [
                    'request' => $request,
                    'module' => 'logistics sheet',
                    'endpoint' => 'Obtener o crear ficha logística',
                ]);
            }

            $logisticsSheet = LogisticsSheet::firstOrCreate(
                ['id_budget' => $idBudget],
                ['token' => (string) Str::uuid()]
            );

            $logisticsSheet->load('budget.place', 'budget.budgetDeliveryData.eventType');

            $publicUrl = rtrim(env('LOGISTICS_SHEET_FRONTEND_URL', ''), '/') . '/ficha-logistica/' . $logisticsSheet->token;

            $mailTo = config('app.env') === 'testing' || config('app.env') === 'local'
                ? env('MAIL_REDIRECT_TO', env('MAIL_FROM_ADDRESS'))
                : $budget->client_mail;

            MailService::sendAndSave($mailTo, new LogisticsSheetRequest($budget, $publicUrl));

            $result = $logisticsSheet->toPresentedArray();
            $result['public_url'] = $publicUrl;
            $result['mail_sent_to'] = $mailTo;

            return ApiResponse::create('Ficha logística enviada al cliente correctamente', 200, $result, [
                'request' => $request,
                'module' => 'logistics sheet',
                'endpoint' => 'Obtener o crear ficha logística',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al enviar la ficha logística', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'logistics sheet',
                'endpoint' => 'Obtener o crear ficha logística',
            ]);
        }
    }

    // A cuántos días del evento se manda cada recordatorio.
    private const REMINDER_DAYS = [7, 3, 1];

    /**
     * Pensado para correr en un cron (una vez por día). Revisa los
     * presupuestos aprobados cuyo evento es exactamente dentro de 7, 3 o 1
     * día, y si la ficha logística todavía no está completa le reenvía el
     * link al cliente (con un encabezado más urgente cuanto más cerca está
     * el evento). No manda dos veces el mismo recordatorio (se guarda en
     * `reminder_days_sent`), y no manda nada si la ficha ya está completa.
     *
     * Sin middleware admin a propósito: la llama un cron externo, no un
     * usuario logueado. Si se configura `LOGISTICS_SHEET_REMINDERS_SECRET`
     * en el `.env`, hay que mandarlo como `?secret=...`.
     */
    public function sendReminders(Request $request)
    {
        try {
            $secret = env('LOGISTICS_SHEET_REMINDERS_SECRET');
            if ($secret && $request->query('secret') !== $secret) {
                return ApiResponse::create('No autorizado', 403, ['error' => 'Secret inválido o faltante'], [
                    'request' => $request,
                    'module' => 'logistics sheet',
                    'endpoint' => 'Enviar recordatorios de ficha logística',
                ]);
            }

            $sent = [];
            $skipped = [];
            $errors = [];

            foreach (self::REMINDER_DAYS as $daysRemaining) {
                $targetDate = now()->addDays($daysRemaining)->toDateString();

                $budgets = Budget::with(['logisticsSheet'])
                    ->where('id_budget_status', 3)
                    ->whereDate('date_event', $targetDate)
                    ->whereNotNull('client_mail')
                    ->get();

                foreach ($budgets as $budget) {
                    try {
                        $logisticsSheet = $budget->logisticsSheet ?: LogisticsSheet::firstOrCreate(
                            ['id_budget' => $budget->id],
                            ['token' => (string) Str::uuid()]
                        );

                        if ($logisticsSheet->is_completed) {
                            $skipped[] = ['id_budget' => $budget->id, 'days_remaining' => $daysRemaining, 'reason' => 'ficha_completa'];
                            continue;
                        }

                        if (in_array($daysRemaining, $logisticsSheet->reminder_days_sent ?? [], true)) {
                            $skipped[] = ['id_budget' => $budget->id, 'days_remaining' => $daysRemaining, 'reason' => 'ya_enviado'];
                            continue;
                        }

                        $publicUrl = rtrim(env('LOGISTICS_SHEET_FRONTEND_URL', ''), '/') . '/ficha-logistica/' . $logisticsSheet->token;

                        $mailTo = config('app.env') === 'testing' || config('app.env') === 'local'
                            ? env('MAIL_REDIRECT_TO', env('MAIL_FROM_ADDRESS'))
                            : $budget->client_mail;

                        MailService::sendAndSave($mailTo, new LogisticsSheetReminder($budget, $publicUrl, $daysRemaining));

                        $reminderDaysSent = $logisticsSheet->reminder_days_sent ?? [];
                        $reminderDaysSent[] = $daysRemaining;
                        $logisticsSheet->reminder_days_sent = $reminderDaysSent;
                        $logisticsSheet->save();

                        $sent[] = ['id_budget' => $budget->id, 'days_remaining' => $daysRemaining, 'mail_sent_to' => $mailTo];
                    } catch (\Exception $e) {
                        Log::error('Error al enviar recordatorio de ficha logística', [
                            'id_budget' => $budget->id,
                            'days_remaining' => $daysRemaining,
                            'error' => $e->getMessage(),
                        ]);
                        $errors[] = ['id_budget' => $budget->id, 'days_remaining' => $daysRemaining, 'error' => $e->getMessage()];
                    }
                }
            }

            return ApiResponse::create('Recordatorios procesados', 200, [
                'sent' => $sent,
                'skipped' => $skipped,
                'errors' => $errors,
            ], [
                'request' => $request,
                'module' => 'logistics sheet',
                'endpoint' => 'Enviar recordatorios de ficha logística',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al procesar los recordatorios', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'logistics sheet',
                'endpoint' => 'Enviar recordatorios de ficha logística',
            ]);
        }
    }
}
