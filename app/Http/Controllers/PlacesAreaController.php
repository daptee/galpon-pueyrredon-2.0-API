<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\PlaceArea;
use Illuminate\Http\Request;
use Validator;

class PlacesAreaController extends Controller
{
    // GET ALL - Obtener todos los registros de places_area con filtro opcional por status
    public function index(Request $request)
    {
        try {
            $query = PlaceArea::query();

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where('name', 'like', '%' . $search . '%');
            }

            $placesAreas = $query->orderBy('name')->get();

            $placesAreas->load(['status']);

            return ApiResponse::create('Lugares de área obtenidos correctamente', 200, $placesAreas, [
                'request' => $request,
                'module' => 'places area',
                'endpoint' => 'Obtener lugares de área',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener los lugares de área', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'places area',
                'endpoint' => 'Obtener lugares de área',
            ]);
        }
    }

    // POST - Crear un nuevo registro en places_area
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()], [
                    'request' => $request,
                    'module' => 'places area',
                    'endpoint' => 'Crear lugar de área',
                ]);
            }

            $placesArea = PlaceArea::create([
                'name' => $request->name,
                'status' => 1, // Predefinido como activo
            ]);
            
            $placesArea->load(['status']);

            return ApiResponse::create('Lugar de área creado correctamente', 201, $placesArea, [
                'request' => $request,
                'module' => 'places area',
                'endpoint' => 'Crear lugar de área',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al crear el lugar de área', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'places area',
                'endpoint' => 'Crear lugar de área',
            ]);
        }
    }

    // PUT - Editar un registro de places_area
    public function update(Request $request, $id)
    {
        try {
            $placesArea = PlaceArea::find($id);

            if (!$placesArea) {
                return ApiResponse::create('Lugar de área no encontrado', 404, ['error' => 'Lugar de área no encontrado'], [
                    'request' => $request,
                    'module' => 'places area',
                    'endpoint' => 'Actualizar lugar de área',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'status' => 'sometimes|integer|in:0,1',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()], [
                    'request' => $request,
                    'module' => 'places area',
                    'endpoint' => 'Actualizar lugar de área',
                ]);
            }

            $placesArea->update($request->only(['name', 'status']));
            
            $placesArea->load(['status']);

            return ApiResponse::create('Lugar de área actualizado correctamente', 200, $placesArea, [
                'request' => $request,
                'module' => 'places area',
                'endpoint' => 'Actualizar lugar de área',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar el lugar de área', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'places area',
                'endpoint' => 'Actualizar lugar de área',
            ]);
        }
    }
}
