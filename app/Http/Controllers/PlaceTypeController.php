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
        try {
            // Parámetros de paginación
            $perPage = $request->query('per_page');
            $page = $request->query('page', 1);

            // Construir la consulta base con relaciones
            $query = PlaceType::with('status');

            // Filtro por estado
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filtro por búsqueda
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where('name', 'like', '%' . $search . '%');
            }

            $query->orderBy('name');

            // Aplicar paginación si se especifica per_page
            if ($perPage) {
                $placesTypes = $query->paginate($perPage, ['*'], 'page', $page);
                $data = $placesTypes->items();
                $meta_data = [
                    'page' => $placesTypes->currentPage(),
                    'per_page' => $placesTypes->perPage(),
                    'total' => $placesTypes->total(),
                    'last_page' => $placesTypes->lastPage(),
                ];
            } else {
                // Si no se especifica per_page, traer todos los registros
                $data = $query->get();
                $meta_data = null;
            }

            return ApiResponse::paginate('Tipos de lugares traídos correctamente', 200, $data, $meta_data, [
                'request' => $request,
                'module' => 'place type',
                'endpoint' => 'Traer tipos de lugares',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al traer los tipos de lugares', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'place type',
                'endpoint' => 'Traer tipos de lugares',
            ]);
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
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request' => $request,
                    'module' => 'place type',
                    'endpoint' => 'Crear tipos de lugares',
                ]);
            }

            $placeType = PlaceType::create([
                'name' => $request->name,
                'status' => $request->status ?? 1,
            ]);

            $placeType->load(['status']);

            return ApiResponse::create('Tipo de lugar creado correctamente', 201, $placeType, [
                'request' => $request,
                'module' => 'place type',
                'endpoint' => 'Crear tipos de lugares',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al crear el tipo de lugar', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'place type',
                'endpoint' => 'Crear tipos de lugares',
            ]);
        }
    }

    // PUT - Actualizar un tipo de lugar
    public function update(Request $request, $id)
    {
        try {
            $placeType = PlaceType::find($id);

            if (!$placeType) {
                return ApiResponse::create('Tipo de lugar no encontrado', 404, ['error' => 'Tipo de lugar no encontrado'], [
                    'request' => $request,
                    'module' => 'place type',
                    'endpoint' => 'Actualizar tipos de lugares',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'status' => 'sometimes|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request' => $request,
                    'module' => 'place type',
                    'endpoint' => 'Actualizar tipos de lugares',
                ]);
            }

            $placeType->update($request->only(['name', 'status']));

            $placeType->load(['status']);

            return ApiResponse::create('Tipo de lugar actualizado correctamente', 201, $placeType, [
                'request' => $request,
                'module' => 'place type',
                'endpoint' => 'Actualizar tipos de lugares',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar el tipo de lugar', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'place type',
                'endpoint' => 'Actualizar tipos de lugares',
            ]);
        }
    }
}
