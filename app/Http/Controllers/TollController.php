<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\Toll;
use Log;
use Validator;

class TollController extends Controller
{
    // GET ALL - Retorna todos los peajes, filtrando por estado si se proporciona
    public function index(Request $request)
    {
        Log::info("Obteniendo lista de peajes");
        try {
            $query = Toll::query();

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $tolls = $query->get();

            $tolls->load(['status']);

            return ApiResponse::create('Peajes traídos correctamente', 200, $tolls, [
                    'request' => $request,
                    'module' => 'toll',
                    'endpoint' => 'Obtener los peajes',
                ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener los peajes', 500, ['error' => $e->getMessage()], [
                    'request' => $request,
                    'module' => 'toll',
                    'endpoint' => 'Obtener los peajes',
                ]);
        }
    }

    // POST - Crear un nuevo peaje
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'cost' => 'required|numeric|min:0',
                'status' => 'nullable|integer|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()], [
                    'request' => $request,
                    'module' => 'toll',
                    'endpoint' => 'Crear un peaje',
                ]);
            }

            $toll = Toll::create([
                'name' => $request->name,
                'cost' => $request->cost,
                'status' => $request->status ?? 1, // Valor por defecto "activo"
            ]);

            $toll->load(['status']);

            return ApiResponse::create('Peaje creado correctamente', 201, $toll, [
                'request' => $request,
                'module' => 'toll',
                'endpoint' => 'Crear un peaje',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al crear el peaje', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'toll',
                'endpoint' => 'Crear un peaje',
            ]);
        }
    }

    // PUT - Actualizar un peaje
    public function update(Request $request, $id)
    {
        try {
            $toll = Toll::find($id);

            if (!$toll) {
                return ApiResponse::create('Peaje no encontrado', 404, ['error' => 'Peaje no encontrado'], [
                    'request' => $request,
                    'module' => 'toll',
                    'endpoint' => 'Actualizar un peaje',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'cost' => 'sometimes|required|numeric|min:0',
                'status' => 'sometimes|integer|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()], [
                    'request' => $request,
                    'module' => 'toll',
                    'endpoint' => 'Actualizar un peaje',
                ]);
            }

            $toll->update($request->only(['name', 'cost', 'status']));

            $toll->load(['status']);

            return ApiResponse::create('Peaje actualizado correctamente', 200, $toll, [
                    'request' => $request,
                    'module' => 'toll',
                    'endpoint' => 'Actualizar un peaje',
                ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar el peaje', 500, ['error' => $e->getMessage()], [
                    'request' => $request,
                    'module' => 'toll',
                    'endpoint' => 'Actualizar un peaje',
                ]);
        }
    }
}
