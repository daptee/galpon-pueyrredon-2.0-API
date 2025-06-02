<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\PaymentMethod;
use Validator;

class PaymentMethodController extends Controller
{
    // GET ALL - Obtener todos los métodos de pago (con buscador por name)
    public function index(Request $request)
    {
        try {
            $query = PaymentMethod::query();

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where('name', 'like', '%' . $search . '%');
            }

            $methods = $query->get();

            $methods->load('status'); // Cargar traducciones si es necesario

            return ApiResponse::create('Métodos de pago obtenidos correctamente', 200, $methods, [
                'request' => $request,
                'module' => 'payment method',
                'endpoint' => 'Obtener métodos de pago',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener los métodos de pago', 500, ['error' => $e->getMessage()]);
        }
    }

    // POST - Crear nuevo método de pago
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:payment_methods,name',
                'status' => 'nullable|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()]);
            }

            $method = PaymentMethod::create([
                'name' => $request->name,
                'status' => $request->status ?? 1,
            ]);

            $method->load('status');

            return ApiResponse::create('Método de pago creado correctamente', 201, $method);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al crear método de pago', 500, ['error' => $e->getMessage()]);
        }
    }

    // PUT - Editar método de pago
    public function update(Request $request, $id)
    {
        try {
            $method = PaymentMethod::find($id);

            if (!$method) {
                return ApiResponse::create('Método de pago no encontrado', 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:payment_methods,name,' . $id,
                'status' => 'nullable|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()]);
            }

            $method->update([
                'name' => $request->name,
                'status' => $request->status ?? $method->status,
            ]);

            $method->load('status');

            return ApiResponse::create('Método de pago actualizado correctamente', 200, $method);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar método de pago', 500, ['error' => $e->getMessage()]);
        }
    }
}
