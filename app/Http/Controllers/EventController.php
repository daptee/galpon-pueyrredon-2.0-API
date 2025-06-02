<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Budget;
use App\Models\Payment;
use Illuminate\Http\Request;
use Validator;
use Log;

class EventController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->query('per_page', 10);
            $page = $request->query('page', 1);

            $query = Budget::with(['place', 'client', 'budgetStatus', 'budgetDeliveryData', 'payments'])
                ->where('id_budget_status', 3); // o el ID del estado aprobado

            // Buscador por ID de presupuesto
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where('id', 'like', '%' . $search . '%');
            }

            // Filtros
            if ($request->has('place')) {
                $query->where('id_place', $request->input('place'));
            }

            if ($request->has('client')) {
                $query->where('id_client', $request->input('client'));
            }

            if ($request->has('date_event')) {
                $query->whereDate('date_event', $request->input('date_event'));
            }

            if ($request->has('start_date')) {
                $query->whereDate('date_event', '>=', $request->input('start_date'));
            }

            if ($request->has('pending_balance') && $request->input('pending_balance') == 1) {
                $query->where(function ($q) {
                    $q->where('total', '>', 0)
                        ->whereRaw('(
                        SELECT COALESCE(SUM(p.amount), 0) 
                        FROM payments p 
                        WHERE p.id_budget = budgets.id 
                        AND p.id_payment_status = 1
                    ) < budgets.total');
                });
            }


            $budgets = $query->paginate($perPage, ['*'], 'page', $page);

            $data = $budgets->items();
            $meta_data = [
                'page' => $budgets->currentPage(),
                'per_page' => $budgets->perPage(),
                'total' => $budgets->total(),
                'last_page' => $budgets->lastPage(),
            ];

            return ApiResponse::paginate('Eventos obtenidos correctamente', 200, $data, $meta_data, [
                'request' => $request,
                'module' => 'event',
                'endpoint' => 'Obtener eventos aprobados',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener eventos', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'event',
                'endpoint' => 'Obtener eventos aprobados',
            ]);
        }
    }

    public function show($id)
    {
        try {
            $budget = Budget::with([
                'client',
                'place',
                'budgetDeliveryData.locality',
                'payments.paymentType',
                'payments.paymentMethod',
                'payments.paymentStatus'
            ])->where('id_budget_status', 3) // O ID del estado aprobado
                ->findOrFail($id);

            return ApiResponse::create('Evento obtenido correctamente', 200, $budget, [
                'module' => 'event',
                'endpoint' => 'Obtener evento por ID',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener el evento', 500, ['error' => $e->getMessage()], [
                'module' => 'event',
                'endpoint' => 'Obtener evento por ID',
            ]);
        }
    }

}
