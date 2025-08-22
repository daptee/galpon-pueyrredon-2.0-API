<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\PaymentStatus;
use Validator;

class PaymentStatusController extends Controller
{
    // GET ALL - Obtener todos los estados de pago (con buscador por name)
    public function index(Request $request)
    {
        try {
            $query = PaymentStatus::query();

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where('name', 'like', '%' . $search . '%');
            }

            $statuses = $query->orderBy('name')->get();

            $statuses->load('status');

            return ApiResponse::create('Estados de pago obtenidos correctamente', 200, $statuses, [
                'request' => $request,
                'module' => 'payment status',
                'endpoint' => 'Obtener estados de pago',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener los estados de pago', 500, ['error' => $e->getMessage()]);
        }
    }

    // POST - Crear nuevo estado de pago
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:payment_status,name',
                'status' => 'nullable|in:1,2,3', // Opcional, si se maneja el estado
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request' => $request,
                    'module' => 'payment status',
                    'endpoint' => 'Crear estado de pago',
                ]);
            }

            $status = PaymentStatus::create([
                'name' => $request->name,
                'status' => $request->status ?? 1, // Asignar estado por defecto si no se proporciona
            ]);

            $status->load('status');

            return ApiResponse::create('Estado de pago creado correctamente', 201, $status, [
                'request' => $request,
                'module' => 'payment status',
                'endpoint' => 'Crear estado de pago',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al crear estado de pago', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'payment status',
                'endpoint' => 'Crear estado de pago',
            ]);
        }
    }

    // PUT - Editar estado de pago
    public function update(Request $request, $id)
    {
        try {
            $status = PaymentStatus::find($id);

            if (!$status) {
                return ApiResponse::create('Estado de pago no encontrado', 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:payment_status,name,' . $id,
                'status' => 'nullable|in:1,2,3', // Opcional, si se maneja el estado
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request' => $request,
                    'module' => 'payment status',
                    'endpoint' => 'Actualizar estado de pago',
                ]);
            }

            $status->update([
                'name' => $request->name,
                'status' => $request->status ?? $status->status, // Mantener el estado actual si no se proporciona uno nuevo
            ]);

            $status->load('status');

            return ApiResponse::create('Estado de pago actualizado correctamente', 200, $status, [
                'request' => $request,
                'module' => 'payment status',
                'endpoint' => 'Actualizar estado de pago',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar estado de pago', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'payment status',
                'endpoint' => 'Actualizar estado de pago',
            ]);
        }
    }
}
