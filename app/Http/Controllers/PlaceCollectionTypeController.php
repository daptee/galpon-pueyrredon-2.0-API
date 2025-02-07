<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\PlaceCollectionType;
use Log;
use Validator;

class PlaceCollectionTypeController extends Controller
{
    // GET ALL - Retorna todos los tipos de cobro de lugares, filtrando por estado si se proporciona
    public function index(Request $request)
    {
        try {
            $query = PlaceCollectionType::query();

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $collectionTypes = $query->get();

            $collectionTypes->load(['status']);

            return ApiResponse::create('Tipos de cobro de lugares traídos correctamente', 200, $collectionTypes, [
                'request' => $request,
                'module' => 'place collection type',
                'endpoint' => 'Obtener tipos de cobro de lugares',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener los tipos de cobro de lugares', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'place collection type',
                'endpoint' => 'Obtener tipos de cobro de lugares',
            ]);
        }
    }

    // POST - Crear un nuevo tipo de cobro de lugar
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'status' => 'nullable|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()], [
                    'request' => $request,
                    'module' => 'place collection type',
                    'endpoint' => 'Crear tipos de cobro de lugares',
                ]);
            }

            $collectionType = PlaceCollectionType::create([
                'name' => $request->name,
                'status' => $request->status ?? 1, // Valor por defecto "activo"
            ]);

            $collectionType->load(['status']);

            return ApiResponse::create('Tipo de cobro de lugar creado correctamente', 201, $collectionType, [
                'request' => $request,
                'module' => 'place collection type',
                'endpoint' => 'Crear tipos de cobro de lugares',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al crear el tipo de cobro de lugar', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'place collection type',
                'endpoint' => 'Crear tipos de cobro de lugares',
            ]);
        }
    }

    // PUT - Actualizar un tipo de cobro de lugar
    public function update(Request $request, $id)
    {
        try {
            $collectionType = PlaceCollectionType::find($id);

            if (!$collectionType) {
                return ApiResponse::create('Tipo de cobro de lugar no encontrado', 404, ['error' => 'Tipo de cobro de lugar no encontrado'], [
                    'request' => $request,
                    'module' => 'place collection type',
                    'endpoint' => 'Actualizar tipos de cobro de lugares',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'status' => 'sometimes|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()], [
                    'request' => $request,
                    'module' => 'place collection type',
                    'endpoint' => 'Actualizar tipos de cobro de lugares',
                ]);
            }

            $collectionType->update($request->only(['name', 'status']));

            $collectionType->load(['status']);

            return ApiResponse::create('Tipo de cobro de lugar actualizado correctamente', 200, $collectionType, [
                'request' => $request,
                'module' => 'place collection type',
                'endpoint' => 'Actualizar tipos de cobro de lugares',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar el tipo de cobro de lugar', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'place collection type',
                'endpoint' => 'Actualizar tipos de cobro de lugares',
            ]);
        }
    }
}
