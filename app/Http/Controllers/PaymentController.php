<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\PaymentStatusHistory;
use Validator;
use Carbon\Carbon;

class PaymentController extends Controller
{
    // GET ALL payments con paginación y filtros
    public function index(Request $request)
    {
        try {
            $perPage = $request->query('per_page', 10);
            $page = $request->query('page', 1);

            $query = Payment::with([
                'budget.budgetStatus',
                'budget.place',
                'budget.transportation',
                'budget.client',
                'budget.budgetProducts.product',
                'paymentType',
                'paymentMethod',
                'paymentStatus',
                'user'
            ]);

            // === Filtros dinámicos ===

            // Filtro por lugar (relacionado al presupuesto)
if ($request->has('place')) {
    $placeId = $request->input('place');
    $query->whereHas('budget.place', function ($q) use ($placeId) {
        $q->where('id', $placeId);
    });
}

// Filtro por cliente (relacionado al presupuesto)
if ($request->has('client')) {
    $clientId = $request->input('client');
    $query->whereHas('budget.client', function ($q) use ($clientId) {
        $q->where('id', $clientId);
    });
}

            // Filtro por fecha de pago (rango)
            if ($request->has('payment_date_start') && $request->has('payment_date_end')) {
                $query->whereBetween('payment_datetime', [
                    $request->input('payment_date_start'),
                    $request->input('payment_date_end')
                ]);
            }

            // Filtro por tipo de pago
            if ($request->has('payment_type')) {
                $query->where('id_payment_type', $request->input('payment_type'));
            }

            // Filtro por forma de pago
            if ($request->has('payment_method')) {
                $query->where('id_payment_method', $request->input('payment_method'));
            }

            // Filtro por estado
            if ($request->has('payment_status')) {
                $query->where('id_payment_status', $request->input('payment_status'));
            }

            // Paginado
            $payments = $query->paginate($perPage, ['*'], 'page', $page);

            $data = $payments->items();
            $meta_data = [
                'page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'last_page' => $payments->lastPage(),
            ];

            return ApiResponse::paginate('Pagos obtenidos correctamente', 200, $data, $meta_data, [
                'request' => $request,
                'module' => 'payment',
                'endpoint' => 'Obtener pagos',
            ]);

        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener los pagos', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'payment',
                'endpoint' => 'Obtener pagos',
            ]);
        }
    }

    // POST nuevo pago
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_budget' => 'required|integer|exists:budgets,id',
                'id_payment_type' => 'required|integer|exists:payment_types,id',
                'id_payment_method' => 'required|integer|exists:payment_methods,id',
                'amount' => 'required|numeric|min:0.01',
                'observations' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()]);
            }
                

            $payment = Payment::create([
                'id_budget' => $request->id_budget,
                'id_user' => auth()->id(),
                'payment_datetime' => now(),
                'id_payment_type' => $request->id_payment_type,
                'id_payment_method' => $request->id_payment_method,
                'amount' => $request->amount,
                'observations' => $request->observations,
                'id_payment_status' => 1, // Ej: "Pagado" o "Activo", ajusta al ID correspondiente
            ]);

            $payment->load([
                'budget.budgetStatus',
                'budget.place',
                'budget.transportation',
                'budget.client',
                'budget.budgetProducts.product',
                'paymentType',
                'paymentMethod',
                'paymentStatus',
                'user'
            ]);

            // Guardar historial de estado
            PaymentStatusHistory::create([
                'id_payment' => $payment->id,
                'id_user' => auth()->id(),
                'id_payment_status' => 1, // Estado inicial, ajusta al ID correspondiente
                'datetime' => now(),
                'observations' => $request->observations,
            ]);

            return ApiResponse::create('Pago registrado correctamente', 201, $payment);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al registrar el pago', 500, ['error' => $e->getMessage()]);
        }
    }

    // PUT actualizar estado del pago (anulación, etc.)
    public function updateStatus(Request $request, $id)
    {
        try {
            $payment = Payment::find($id);
            if (!$payment) {
                return ApiResponse::create('Pago no encontrado', 404);
            }

            $validator = Validator::make($request->all(), [
                'id_payment_status' => 'required|integer|exists:payment_status,id',
                'observations' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()]);
            }

            // Guardar historial
            PaymentStatusHistory::create([
                'id_payment' => $payment->id,
                'id_user' => auth()->id(),
                'id_payment_status' => $request->id_payment_status,
                'datetime' => now(),
                'observations' => $request->observations,
            ]);

            // Actualizar estado
            $payment->update([
                'id_payment_status' => $request->id_payment_status,
            ]);

            $payment->load([
                'budget.budgetStatus',
                'budget.place',
                'budget.transportation',
                'budget.client',
                'budget.budgetProducts.product',
                'paymentType',
                'paymentMethod',
                'paymentStatus',
                'user'
            ]);

            return ApiResponse::create('Estado del pago actualizado correctamente', 200, $payment);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar el estado del pago', 500, ['error' => $e->getMessage()]);
        }
    }
}
