<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\BlockedDate;
use App\Services\LogisticsCapacityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LogisticsCapacityController extends Controller
{
    private const VARIABLE_LABELS = [
        'daily_events' => 'Capacidad máxima de eventos diarios',
        'daily_volume' => 'Volumen máximo diario',
        'successive_events' => 'Capacidad de eventos x jornadas sucesivas',
        'successive_volume' => 'Volumen x jornadas sucesivas',
    ];

    private const STATUS_LABELS = [
        'alta_demanda' => 'Alta Demanda',
        'restringida' => 'Disponibilidad Restringida',
        'excedida' => 'Disponibilidad Excedida',
    ];

    private function isClient(): bool
    {
        return auth()->user()->id_user_type == 3;
    }

    private function forbiddenForClients()
    {
        if ($this->isClient()) {
            return ApiResponse::create('No autorizado', 403, ['error' => 'Los clientes no tienen acceso a este recurso'], [
                'module' => 'logistics-capacity',
            ]);
        }

        return null;
    }

    public function getConfig(Request $request)
    {
        if ($response = $this->forbiddenForClients()) {
            return $response;
        }

        try {
            $service = new LogisticsCapacityService();
            $config = $service->getConfig();

            return ApiResponse::create('Configuración de capacidad logística obtenida correctamente', 200, $config, [
                'request' => $request,
                'module' => 'logistics-capacity',
                'endpoint' => 'Obtener configuración de capacidad logística',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener la configuración', 500, ['error' => $e->getMessage()], [
                'module' => 'logistics-capacity',
                'endpoint' => 'Obtener configuración de capacidad logística',
            ]);
        }
    }

    public function updateConfig(Request $request)
    {
        if ($response = $this->forbiddenForClients()) {
            return $response;
        }

        try {
            $validator = Validator::make($request->all(), [
                'max_daily_events' => 'sometimes|required|integer|min:0',
                'max_daily_volume' => 'sometimes|required|numeric|min:0',
                'max_successive_events' => 'sometimes|required|integer|min:0',
                'max_successive_volume' => 'sometimes|required|numeric|min:0',
                'high_demand_threshold' => 'sometimes|required|numeric|min:0|max:100',
                'restricted_threshold' => 'sometimes|required|numeric|min:0|max:100',
            ]);

            $validator->after(function ($validator) use ($request) {
                $service = new LogisticsCapacityService();
                $current = $service->getConfig();

                $high = $request->input('high_demand_threshold', $current->high_demand_threshold);
                $restricted = $request->input('restricted_threshold', $current->restricted_threshold);

                if ((float) $restricted < (float) $high) {
                    $validator->errors()->add('restricted_threshold', 'El umbral de disponibilidad restringida debe ser mayor o igual al de alta demanda.');
                }
            });

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request' => $request,
                    'module' => 'logistics-capacity',
                    'endpoint' => 'Actualizar configuración de capacidad logística',
                ]);
            }

            $service = new LogisticsCapacityService();
            $config = $service->getConfig();
            $config->update($request->only([
                'max_daily_events',
                'max_daily_volume',
                'max_successive_events',
                'max_successive_volume',
                'high_demand_threshold',
                'restricted_threshold',
            ]));

            return ApiResponse::create('Configuración de capacidad logística actualizada correctamente', 200, $config, [
                'request' => $request,
                'module' => 'logistics-capacity',
                'endpoint' => 'Actualizar configuración de capacidad logística',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar la configuración', 500, ['error' => $e->getMessage()], [
                'module' => 'logistics-capacity',
                'endpoint' => 'Actualizar configuración de capacidad logística',
            ]);
        }
    }

    public function listBlockedDates(Request $request)
    {
        if ($response = $this->forbiddenForClients()) {
            return $response;
        }

        try {
            $query = BlockedDate::query()->orderBy('date');

            if ($request->has('month') && $request->has('year')) {
                $query->whereMonth('date', $request->input('month'))
                    ->whereYear('date', $request->input('year'));
            } elseif ($request->has('from') && $request->has('to')) {
                $query->whereBetween('date', [$request->input('from'), $request->input('to')]);
            }

            $blockedDates = $query->get();

            return ApiResponse::create('Fechas cerradas obtenidas correctamente', 200, $blockedDates, [
                'request' => $request,
                'module' => 'logistics-capacity',
                'endpoint' => 'Listar fechas cerradas',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener las fechas cerradas', 500, ['error' => $e->getMessage()], [
                'module' => 'logistics-capacity',
                'endpoint' => 'Listar fechas cerradas',
            ]);
        }
    }

    public function blockDate(Request $request)
    {
        if ($response = $this->forbiddenForClients()) {
            return $response;
        }

        try {
            $validator = Validator::make($request->all(), [
                'date' => 'required|date',
                'reason' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request' => $request,
                    'module' => 'logistics-capacity',
                    'endpoint' => 'Cerrar fecha',
                ]);
            }

            $existing = BlockedDate::whereDate('date', $request->date)->first();
            if ($existing) {
                return ApiResponse::create('La fecha ya se encuentra cerrada', 409, ['error' => 'La fecha ya se encuentra cerrada'], [
                    'request' => $request,
                    'module' => 'logistics-capacity',
                    'endpoint' => 'Cerrar fecha',
                ]);
            }

            $blockedDate = BlockedDate::create([
                'date' => $request->date,
                'reason' => $request->reason,
                'id_user' => auth()->user()->id,
            ]);

            return ApiResponse::create('Fecha cerrada correctamente', 201, $blockedDate, [
                'request' => $request,
                'module' => 'logistics-capacity',
                'endpoint' => 'Cerrar fecha',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al cerrar la fecha', 500, ['error' => $e->getMessage()], [
                'module' => 'logistics-capacity',
                'endpoint' => 'Cerrar fecha',
            ]);
        }
    }

    public function unblockDate(Request $request, $id)
    {
        if ($response = $this->forbiddenForClients()) {
            return $response;
        }

        try {
            $blockedDate = BlockedDate::find($id);

            if (!$blockedDate) {
                return ApiResponse::create('Fecha cerrada no encontrada', 404, ['error' => 'Fecha cerrada no encontrada'], [
                    'module' => 'logistics-capacity',
                    'endpoint' => 'Reabrir fecha',
                ]);
            }

            $blockedDate->delete();

            return ApiResponse::create('Fecha reabierta correctamente', 200, ['id' => (int) $id], [
                'request' => $request,
                'module' => 'logistics-capacity',
                'endpoint' => 'Reabrir fecha',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al reabrir la fecha', 500, ['error' => $e->getMessage()], [
                'module' => 'logistics-capacity',
                'endpoint' => 'Reabrir fecha',
            ]);
        }
    }

    public function check(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'date_event' => 'required|date',
                'id_budget' => 'nullable|integer|exists:budgets,id',
                'volume' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request' => $request,
                    'module' => 'logistics-capacity',
                    'endpoint' => 'Chequear capacidad logística',
                ]);
            }

            $isClient = $this->isClient();
            $service = new LogisticsCapacityService();

            if ($service->familyHasApprovedVersion($request->id_budget)) {
                return ApiResponse::create('Chequeo omitido: la fecha ya fue reservada por una versión aprobada previamente', 200, [
                    'status' => 'skipped',
                    'blocked' => false,
                    'message' => null,
                ], [
                    'request' => $request,
                    'module' => 'logistics-capacity',
                    'endpoint' => 'Chequear capacidad logística',
                ]);
            }

            $result = $service->evaluate($request->date_event, $isClient, (float) ($request->volume ?? 0));

            [$blocked, $message] = $this->buildRoleMessage($result, $isClient);

            $response = array_merge($result, [
                'blocked' => $blocked,
                'message' => $message,
            ]);

            if ($isClient) {
                unset($response['metrics'], $response['violated']);
            }

            return ApiResponse::create('Chequeo de capacidad logística realizado', 200, $response, [
                'request' => $request,
                'module' => 'logistics-capacity',
                'endpoint' => 'Chequear capacidad logística',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al chequear la capacidad logística', 500, ['error' => $e->getMessage()], [
                'module' => 'logistics-capacity',
                'endpoint' => 'Chequear capacidad logística',
            ]);
        }
    }

    private function buildRoleMessage(array $result, bool $isClient): array
    {
        $status = $result['status'];

        if ($status === 'fecha_cerrada') {
            $reason = $result['blocked_date']['reason'] ?? null;
            $message = 'La fecha seleccionada se encuentra cerrada para nuevos eventos.' . ($reason ? " Motivo: {$reason}" : '');
            return [true, $message];
        }

        if ($isClient) {
            if ($status === 'excedida') {
                return [true, 'Nuestro stock y la capacidad logística se encuentra altamente limitada para esta fecha, por favor comuníquese con nosotros para ver si es posible tomar el pedido.'];
            }

            if (in_array($status, ['alta_demanda', 'restringida'])) {
                return [false, 'Todavía tenemos disponibilidad para la fecha pero se trata de una fecha con alta demanda de eventos, tenga en cuenta que la disponibilidad puede afectarse en el corto plazo'];
            }

            return [false, null];
        }

        // Admin / staff: nunca se bloquea salvo fecha cerrada, pero se informa siempre el detalle.
        if (in_array($status, ['alta_demanda', 'restringida', 'excedida'])) {
            $details = collect($result['violated'])
                ->map(fn($v) => (self::VARIABLE_LABELS[$v['variable']] ?? $v['variable']) . " ({$v['percentage']}%)")
                ->unique()
                ->implode(', ');

            $message = 'Situación: ' . self::STATUS_LABELS[$status] . '. Variables que determinan esta situación: ' . $details . '.';
            return [false, $message];
        }

        return [false, null];
    }
}
