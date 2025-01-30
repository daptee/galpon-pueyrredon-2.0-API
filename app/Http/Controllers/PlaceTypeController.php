<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\PlaceType;
use Log;
use Validator;

class PlaceTypeController extends Controller
{
    // GET ALL - Retorna todos los tipos de lugares, filtrando por estado si se proporciona
    public function index(Request $request)
    
    {
        Log::info("aquiiiii");
        try {
            $query = PlaceType::query();

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $placesTypes = $query->get();

            $placesTypes->load(['status']);

            return ApiResponse::create('Tipos de lugares traídos correctamente', 200, $placesTypes);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al crear el tipo de lugar', 500, ['error' => $e->getMessage()]);
        }
    }

    // POST - Crear un nuevo tipo de lugar
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'status' => 'nullable|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validacion', 422, ['error' => $validator->errors()]);
            }

            $placeType = PlaceType::create([
                'name' => $request->name,
                'status' => $request->status ?? 1,
            ]);

            $placeType->load(['status']);

            return ApiResponse::create('Tipo de lugar creado correctamente', 201, $placeType);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al crear el tipo de lugar',  500, ['error' => $e->getMessage()]);
        }
    }

    // PUT - Actualizar un tipo de lugar
    public function update(Request $request, $id)
    {
        try {
            $placeType = PlaceType::find($id);

            if (!$placeType) {
                return ApiResponse::create('Tipo de lugar no encontrado', 404, ['error' => 'Tipo de lugar no encontrado']);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'status' => 'sometimes|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validacion', 422, ['error' => $validator->errors()]);
            }

            $placeType->update($request->only(['name', 'status']));

            $placeType->load(['status']);

            return ApiResponse::create('Tipo de lugar editado correctamente', 201, $placeType);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al editar el tipo de lugar',  500, ['error' => $e->getMessage()]);
        }
    }
}
