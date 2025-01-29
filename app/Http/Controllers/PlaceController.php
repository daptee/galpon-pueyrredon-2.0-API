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
            // Obtén la página y el número de resultados por página desde la query string con valores por defecto
            $perPage = $request->query('per_page', 10);
            $page = $request->query('page', 1);

            // Obtener lugares con sus relaciones y paginación personalizada
            $places = Place::with([
                'placeType',
                'province',
                'locality',
                'tolls',
                'placeCollectionType'
            ])->paginate($perPage, ['*'], 'page', $page);

            // Extraer los datos de la paginación
            $data = $places->items();
            $meta_data = [
                'page' => $places->currentPage(),
                'per_page' => $places->perPage(),
                'total' => $places->total(),
                'last_page' => $places->lastPage(),
            ];

            return ApiResponse::paginate('Lugares obtenidos correctamente', 200, $data, $meta_data);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener los lugares', 500, ['error' => $e->getMessage()]);
        }
    }


    // GET BY ID - Obtener un lugar específico por ID con sus relaciones
    public function show($id)
    {
        Log::info("Obteniendo información del lugar con ID: $id");

        try {
            $place = Place::with([
                'placeType',
                'province',
                'locality',
                'tolls',
                'placeCollectionType'
            ])->find($id);

            if (!$place) {
                return ApiResponse::create('Lugar no encontrado', 404, []);
            }

            return ApiResponse::create('Lugar obtenido correctamente', 200, $place);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener el lugar', 500, ['error' => $e->getMessage()]);
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
                'toll' => 'array',
                'toll.*' => 'integer|exists:tolls,id',
                'distance' => 'nullable|numeric|min:0',
                'travel_time' => 'nullable|date_format:H:i:s',
                'address' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'complexity_factor' => 'nullable|numeric|min:0|max:99.99',
                'observations' => 'nullable|string',
                'status' => 'nullable|integer|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()]);
            }

            $place = Place::create([
                'id_place_type' => $request->id_place_type,
                'name' => $request->name,
                'id_province' => $request->id_province,
                'id_locality' => $request->id_locality,
                'id_place_collection_type' => $request->id_place_collection_type,
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
                'placeType',
                'province',
                'locality',
                'tolls',
                'placeCollectionType'
            ]);

            return ApiResponse::create('Lugar creado correctamente', 201, $place);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al crear el lugar', 500, ['error' => $e->getMessage()]);
        }
    }

    // PUT - Editar un lugar
    public function update(Request $request, $id)
    {
        try {
            $place = Place::find($id);

            if (!$place) {
                return ApiResponse::create('Lugar no encontrado', 404, []);
            }

            $validator = Validator::make($request->all(), [
                'id_place_type' => 'sometimes|required|exists:places_types,id',
                'name' => 'sometimes|required|string|max:255',
                'id_province' => 'sometimes|required|exists:provinces,id',
                'id_locality' => 'sometimes|required|exists:localities,id',
                'id_place_collection_type' => 'sometimes|required|exists:places_collections_types,id',
                'toll' => 'array',
                'toll.*' => 'integer|exists:tolls,id',
                'distance' => 'nullable|numeric|min:0',
                'travel_time' => 'nullable|date_format:H:i:s',
                'address' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'complexity_factor' => 'nullable|numeric|min:0|max:99.99',
                'observations' => 'nullable|string',
                'status' => 'nullable|integer|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()]);
            }

            $place->update($request->only([
                'id_place_type',
                'name',
                'id_province',
                'id_locality',
                'id_place_collection_type',
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
                'placeType',
                'province',
                'locality',
                'tolls',
                'placeCollectionType'
            ]);

            return ApiResponse::create('Lugar actualizado correctamente', 200, $place);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar el lugar', 500, ['error' => $e->getMessage()]);
        }
    }
}
