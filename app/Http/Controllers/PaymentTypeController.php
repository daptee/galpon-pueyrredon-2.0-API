<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\PaymentType;
use Illuminate\Http\Request;
use Validator;
use Log;

class PaymentTypeController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Parámetros de paginación
            $perPage = $request->query('per_page');
            $page = $request->query('page', 1);

            // Construir la consulta base con relaciones
            $query = PaymentType::with('status');

            // Filtro por búsqueda
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where('name', 'like', '%' . $search . '%');
            }

            $query->orderBy('name');

            // Aplicar paginación si se envía per_page
            if ($perPage) {
                $paymentTypes = $query->paginate($perPage, ['*'], 'page', $page);
                $data = $paymentTypes->items();
                $meta_data = [
                    'page' => $paymentTypes->currentPage(),
                    'per_page' => $paymentTypes->perPage(),
                    'total' => $paymentTypes->total(),
                    'last_page' => $paymentTypes->lastPage(),
                ];
            } else {
                // Si no se especifica per_page, traer todos los registros
                $data = $query->get();
                $meta_data = null;
            }

            return ApiResponse::paginate('Tipos de pago traídos correctamente', 200, $data, $meta_data, [
                'request' => $request,
                'module' => 'payment type',
                'endpoint' => 'Traer tipos de pago',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al traer los tipos de pago', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'payment type',
                'endpoint' => 'Traer tipos de pago',
            ]);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:payment_types,name',
                'status' => 'nullable|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()], [
                    'request' => $request,
                    'module' => 'payment type',
                    'endpoint' => 'Crear tipo de pago',
                ]);
            }

            $paymentType = PaymentType::create([
                'name' => $request->name,
                'status' => $request->status ?? 1,
            ]);

            $paymentType->load('status');

            return ApiResponse::create('Tipo de pago creado correctamente', 201, $paymentType, [
                'request' => $request,
                'module' => 'payment type',
                'endpoint' => 'Crear tipo de pago',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al crear el tipo de pago', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'payment type',
                'endpoint' => 'Crear tipo de pago',
            ]);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $paymentType = PaymentType::find($id);

            if (!$paymentType) {
                return ApiResponse::create('Tipo de pago no encontrado', 404, ['error' => 'Tipo de pago no encontrado'], [
                    'request' => $request,
                    'module' => 'payment type',
                    'endpoint' => 'Actualizar tipo de pago',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255|unique:payment_types,name,' . $id,
                'status' => 'sometimes|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()], [
                    'request' => $request,
                    'module' => 'payment type',
                    'endpoint' => 'Actualizar tipo de pago',
                ]);
            }

            $paymentType->update($request->only(['name', 'status']));

            $paymentType->load('status');

            return ApiResponse::create('Tipo de pago actualizado correctamente', 200, $paymentType, [
                'request' => $request,
                'module' => 'payment type',
                'endpoint' => 'Actualizar tipo de pago',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar el tipo de pago', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'payment type',
                'endpoint' => 'Actualizar tipo de pago',
            ]);
        }
    }

}
