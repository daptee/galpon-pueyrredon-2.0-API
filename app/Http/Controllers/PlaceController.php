<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\PlacesTolls;
use Illuminate\Http\Request;
use App\Models\Place;
use Log;
use Validator;

class PlaceController extends Controller
{
    // GET ALL - Listar lugares con sus relaciones, con paginación
    public function index(Request $request)
    {
        try {
            // Obtén la página y el número de resultados por página con valores por defecto
            $perPage = $request->query('per_page');
            $page = $request->query('page', 1);

            // Construir la consulta base con relaciones
            $query = Place::with([
                'placeType.status',
                'province',
                'locality',
                'tolls.status',
                'placeCollectionType.status',
                'placeArea.status',
                'status'
            ]);

            // Aplicar filtros opcionales
            if ($request->has('place_type')) {
                $query->where('id_place_type', $request->input('place_type'));
            }
            if ($request->has('id_province')) {
                $query->where('id_province', $request->input('id_province'));
            }
            if ($request->has('collection_type')) {
                $query->where('id_place_collection_type', $request->input('collection_type'));
            }
            if ($request->has('place_area')) {
                $query->where('id_place_area', $request->input('place_area'));
            }
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where('name', 'like', '%' . $search . '%');
            }

            // Aplicar paginación con los filtros
            if ($perPage) {
                // Paginado normal
                $places = $query->paginate($perPage, ['*'], 'page', $page);
                $data = $places->items();
                $meta_data = [
                    'page' => $places->currentPage(),
                    'per_page' => $places->perPage(),
                    'total' => $places->total(),
                    'last_page' => $places->lastPage(),
                ];
            } else {
                // Sin paginar, traer todo
                $data = $query->get();
                $meta_data = null;
            }

            return ApiResponse::paginate('Lugares obtenidos correctamente', 200, $data, $meta_data, [
                'request' => $request,
                'module' => 'place',
                'endpoint' => 'Obtener lugares',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener los lugares', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'place',
                'endpoint' => 'Obtener lugares',
            ]);
        }
    }

    // GET BY ID - Obtener un lugar específico por ID con sus relaciones
    public function show($id, Request $request)
    {
        Log::info("Obteniendo información del lugar con ID: $id");

        try {
            $place = Place::with([
                'placeType.status',
                'province',
                'locality',
                'tolls.status',
                'placeCollectionType.status',
                'placeArea.status',
                'status'
            ])->find($id);

            if (!$place) {
                return ApiResponse::create('Lugar no encontrado', 404, [], [
                    'request' => $request,
                    'module' => 'place',
                    'endpoint' => 'Obtener un lugar',
                ]);
            }

            return ApiResponse::create('Lugar obtenido correctamente', 200, $place, [
                'request' => $request,
                'module' => 'place',
                'endpoint' => 'Obtener un lugar',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener el lugar', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'place',
                'endpoint' => 'Obtener un luga',
            ]);
        }
    }

    // POST - Crear un nuevo lugar
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_place_type' => 'required|exists:places_types,id',
                'name' => 'required|string|max:255',
                'id_province' => 'required|exists:provinces,id',
                'id_locality' => 'required|exists:localities,id',
                'id_place_collection_type' => 'required|exists:places_collections_types,id',
                'id_place_area' => 'required|exists:places_area,id',
                'toll' => 'array',
                'toll.*' => 'integer|exists:tolls,id',
                'distance' => 'nullable|numeric|min:0',
                'travel_time' => 'nullable|string|max:255',
                'address' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'complexity_factor' => 'nullable|numeric|min:0|max:99.99',
                'observations' => 'nullable|string',
                'status' => 'nullable|integer|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()], [
                    'request' => $request,
                    'module' => 'place',
                    'endpoint' => 'Crear un lugar',
                ]);
            }

            $place = Place::create([
                'id_place_type' => $request->id_place_type,
                'name' => $request->name,
                'id_province' => $request->id_province,
                'id_locality' => $request->id_locality,
                'id_place_collection_type' => $request->id_place_collection_type,
                'id_place_area' => $request->id_place_area,
                'distance' => $request->distance,
                'travel_time' => $request->travel_time,
                'address' => $request->address,
                'phone' => $request->phone,
                'complexity_factor' => $request->complexity_factor,
                'observations' => $request->observations,
                'status' => $request->status ?? 1, // Estado activo por defecto
            ]);

            if (!empty($request->toll)) {
                foreach ($request->toll as $id_toll) {
                    PlacesTolls::create([
                        'id_place' => $place->id,
                        'id_toll' => $id_toll
                    ]);
                }
            }

            $place->load([
                'placeType.status',
                'province',
                'locality',
                'tolls.status',
                'placeCollectionType.status',
                'placeArea.status',
                'status'
            ]);

            return ApiResponse::create('Lugar creado correctamente', 201, $place, [
                'request' => $request,
                'module' => 'place',
                'endpoint' => 'Crear un lugar',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al crear el lugar', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'place',
                'endpoint' => 'Crear un lugar',
            ]);
        }
    }

    // PUT - actualizar un lugar
    public function update(Request $request, $id)
    {
        try {
            $place = Place::find($id);

            if (!$place) {
                return ApiResponse::create('Lugar no encontrado', 404, [], [
                    'request' => $request,
                    'module' => 'place',
                    'endpoint' => 'Actualizar un lugar',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'id_place_type' => 'sometimes|required|exists:places_types,id',
                'name' => 'sometimes|required|string|max:255',
                'id_province' => 'sometimes|required|exists:provinces,id',
                'id_locality' => 'sometimes|required|exists:localities,id',
                'id_place_collection_type' => 'sometimes|required|exists:places_collections_types,id',
                'id_place_area' => 'sometimes|required|exists:places_area,id',
                'toll' => 'array',
                'toll.*' => 'integer|exists:tolls,id',
                'distance' => 'nullable|numeric|min:0',
                'travel_time' => 'nullable|string|max:255',
                'address' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'complexity_factor' => 'nullable|numeric|min:0|max:99.99',
                'observations' => 'nullable|string',
                'status' => 'nullable|integer|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()], [
                    'request' => $request,
                    'module' => 'place',
                    'endpoint' => 'Actualizar un lugar',
                ]);
            }

            $place->update($request->only([
                'id_place_type',
                'name',
                'id_province',
                'id_locality',
                'id_place_collection_type',
                'id_place_area',
                'distance',
                'travel_time',
                'address',
                'phone',
                'complexity_factor',
                'observations',
                'status'
            ]));

            if ($request->has('toll')) {
                $newTolls = $request->toll;

                // Eliminar tolls que no estén en la lista enviada
                PlacesTolls::where('id_place', $id)->whereNotIn('id_toll', $newTolls)->delete();

                // Agregar tolls nuevos si no existen en la relación
                foreach ($newTolls as $id_toll) {
                    PlacesTolls::firstOrCreate([
                        'id_place' => $id,
                        'id_toll' => $id_toll
                    ]);
                }
            } else {
                // Si no se envía "toll", eliminar todas las relaciones
                PlacesTolls::where('id_place', $id)->delete();
            }

            $place->load([
                'placeType.status',
                'province',
                'locality',
                'tolls.status',
                'placeCollectionType.status',
                'placeArea.status',
                'status'
            ]);

            return ApiResponse::create('Lugar actualizado correctamente', 200, $place, [
                'request' => $request,
                'module' => 'place',
                'endpoint' => 'Actualizar un lugar',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar el lugar', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'place',
                'endpoint' => 'Actualizar un lugar',
            ]);
        }
    }
}
