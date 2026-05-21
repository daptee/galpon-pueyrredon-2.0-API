<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\ClientPlaceTransportPrice;
use App\Models\ClientPlaceTransportPriceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClientPlaceTransportPriceController extends Controller
{
    // GET / - Listar todos con filtros y paginación
    public function index(Request $request)
    {
        try {
            $perPage = $request->query('per_page');
            $page = $request->query('page', 1);

            $query = ClientPlaceTransportPrice::with(['client', 'place', 'items']);

            if ($request->has('id_client')) {
                $query->where('id_client', $request->input('id_client'));
            }

            if ($request->has('id_place')) {
                $query->where('id_place', $request->input('id_place'));
            }

            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($perPage) {
                $records = $query->paginate($perPage, ['*'], 'page', $page);
                $data = $records->items();
                $meta_data = [
                    'page' => $records->currentPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                    'last_page' => $records->lastPage(),
                ];
            } else {
                $data = $query->get();
                $meta_data = null;
            }

            return ApiResponse::paginate('Precios fijos de traslado obtenidos correctamente', 200, $data, $meta_data, [
                'request' => $request,
                'module' => 'client_place_transport_prices',
                'endpoint' => 'Listar precios fijos de traslado',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener precios fijos de traslado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'client_place_transport_prices',
                'endpoint' => 'Listar precios fijos de traslado',
            ]);
        }
    }

    // GET /check - Verificar si existe precio fijo para client+place+volume
    public function check(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_client' => 'required|integer|exists:clients,id',
                'id_place'  => 'required|integer|exists:places,id',
                'volume'    => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request' => $request,
                    'module' => 'client_place_transport_prices',
                    'endpoint' => 'Verificar precio fijo de traslado',
                ]);
            }

            $header = ClientPlaceTransportPrice::with(['items', 'client', 'place'])
                ->where('id_client', $request->input('id_client'))
                ->where('id_place', $request->input('id_place'))
                ->where('status', 1)
                ->first();

            if (!$header) {
                return ApiResponse::create('No existe precio fijo para esta combinación de cliente y lugar', 200, [
                    'has_fixed_price' => false,
                    'header' => null,
                    'applicable_item' => null,
                ], [
                    'request' => $request,
                    'module' => 'client_place_transport_prices',
                    'endpoint' => 'Verificar precio fijo de traslado',
                ]);
            }

            $volume = (float) $request->input('volume');

            // Buscar el ítem de menor max_volume que sea MAYOR al volumen dado
            $applicableItem = $header->items
                ->where('max_volume', '>', $volume)
                ->sortBy('max_volume')
                ->first();

            return ApiResponse::create('Verificación de precio fijo completada', 200, [
                'has_fixed_price' => $applicableItem !== null,
                'header' => $header,
                'applicable_item' => $applicableItem,
            ], [
                'request' => $request,
                'module' => 'client_place_transport_prices',
                'endpoint' => 'Verificar precio fijo de traslado',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al verificar precio fijo de traslado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'client_place_transport_prices',
                'endpoint' => 'Verificar precio fijo de traslado',
            ]);
        }
    }

    // GET /{id} - Obtener uno con sus ítems
    public function show($id, Request $request)
    {
        try {
            $record = ClientPlaceTransportPrice::with(['client', 'place', 'items'])->find($id);

            if (!$record) {
                return ApiResponse::create('Precio fijo de traslado no encontrado', 404, [], [
                    'request' => $request,
                    'module' => 'client_place_transport_prices',
                    'endpoint' => 'Obtener precio fijo de traslado',
                ]);
            }

            return ApiResponse::create('Precio fijo de traslado obtenido correctamente', 200, $record, [
                'request' => $request,
                'module' => 'client_place_transport_prices',
                'endpoint' => 'Obtener precio fijo de traslado',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener precio fijo de traslado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'client_place_transport_prices',
                'endpoint' => 'Obtener precio fijo de traslado',
            ]);
        }
    }

    // POST / - Crear cabecera + ítems
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_client'    => 'required|integer|exists:clients,id',
                'id_place'     => 'required|integer|exists:places,id',
                'observations' => 'nullable|string',
                'status'       => 'sometimes|integer|in:1,2',
                'items'        => 'required|array|min:1',
                'items.*.max_volume' => 'required|numeric|min:0',
                'items.*.price'      => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request' => $request,
                    'module' => 'client_place_transport_prices',
                    'endpoint' => 'Crear precio fijo de traslado',
                ]);
            }

            // Verificar que no exista ya una combinación activa para este cliente+lugar
            $existing = ClientPlaceTransportPrice::where('id_client', $request->id_client)
                ->where('id_place', $request->id_place)
                ->first();

            if ($existing) {
                return ApiResponse::create('Ya existe un registro de precio fijo para esta combinación de cliente y lugar', 422, [
                    'error' => 'Duplicate client-place combination',
                    'existing_id' => $existing->id,
                ], [
                    'request' => $request,
                    'module' => 'client_place_transport_prices',
                    'endpoint' => 'Crear precio fijo de traslado',
                ]);
            }

            $header = ClientPlaceTransportPrice::create([
                'id_client'    => $request->id_client,
                'id_place'     => $request->id_place,
                'observations' => $request->observations,
                'status'       => $request->status ?? 1,
            ]);

            foreach ($request->items as $item) {
                ClientPlaceTransportPriceItem::create([
                    'id_client_place_transport_price' => $header->id,
                    'max_volume' => $item['max_volume'],
                    'price'      => $item['price'],
                ]);
            }

            $header->load(['client', 'place', 'items']);

            return ApiResponse::create('Precio fijo de traslado creado correctamente', 201, $header, [
                'request' => $request,
                'module' => 'client_place_transport_prices',
                'endpoint' => 'Crear precio fijo de traslado',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al crear precio fijo de traslado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'client_place_transport_prices',
                'endpoint' => 'Crear precio fijo de traslado',
            ]);
        }
    }

    // PUT /{id} - Actualizar cabecera e ítems (reemplaza los ítems)
    public function update(Request $request, $id)
    {
        try {
            $record = ClientPlaceTransportPrice::find($id);

            if (!$record) {
                return ApiResponse::create('Precio fijo de traslado no encontrado', 404, [], [
                    'request' => $request,
                    'module' => 'client_place_transport_prices',
                    'endpoint' => 'Actualizar precio fijo de traslado',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'id_client'    => 'sometimes|integer|exists:clients,id',
                'id_place'     => 'sometimes|integer|exists:places,id',
                'observations' => 'nullable|string',
                'status'       => 'sometimes|integer|in:1,2',
                'items'        => 'sometimes|array|min:1',
                'items.*.max_volume' => 'required_with:items|numeric|min:0',
                'items.*.price'      => 'required_with:items|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request' => $request,
                    'module' => 'client_place_transport_prices',
                    'endpoint' => 'Actualizar precio fijo de traslado',
                ]);
            }

            // Verificar unicidad si se cambia cliente o lugar
            $newClient = $request->input('id_client', $record->id_client);
            $newPlace  = $request->input('id_place', $record->id_place);

            if ($newClient != $record->id_client || $newPlace != $record->id_place) {
                $existing = ClientPlaceTransportPrice::where('id_client', $newClient)
                    ->where('id_place', $newPlace)
                    ->where('id', '!=', $id)
                    ->first();

                if ($existing) {
                    return ApiResponse::create('Ya existe un registro de precio fijo para esta combinación de cliente y lugar', 422, [
                        'error' => 'Duplicate client-place combination',
                        'existing_id' => $existing->id,
                    ], [
                        'request' => $request,
                        'module' => 'client_place_transport_prices',
                        'endpoint' => 'Actualizar precio fijo de traslado',
                    ]);
                }
            }

            $record->update($request->only(['id_client', 'id_place', 'observations', 'status']));

            // Si vienen ítems, reemplazar todos
            if ($request->has('items')) {
                ClientPlaceTransportPriceItem::where('id_client_place_transport_price', $record->id)->delete();

                foreach ($request->items as $item) {
                    ClientPlaceTransportPriceItem::create([
                        'id_client_place_transport_price' => $record->id,
                        'max_volume' => $item['max_volume'],
                        'price'      => $item['price'],
                    ]);
                }
            }

            $record->load(['client', 'place', 'items']);

            return ApiResponse::create('Precio fijo de traslado actualizado correctamente', 200, $record, [
                'request' => $request,
                'module' => 'client_place_transport_prices',
                'endpoint' => 'Actualizar precio fijo de traslado',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar precio fijo de traslado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'client_place_transport_prices',
                'endpoint' => 'Actualizar precio fijo de traslado',
            ]);
        }
    }

    // DELETE /{id} - Desactivar (soft delete: status=2)
    public function destroy($id, Request $request)
    {
        try {
            $record = ClientPlaceTransportPrice::find($id);

            if (!$record) {
                return ApiResponse::create('Precio fijo de traslado no encontrado', 404, [], [
                    'request' => $request,
                    'module' => 'client_place_transport_prices',
                    'endpoint' => 'Eliminar precio fijo de traslado',
                ]);
            }

            $record->status = 2;
            $record->save();

            return ApiResponse::create('Precio fijo de traslado desactivado correctamente', 200, $record, [
                'request' => $request,
                'module' => 'client_place_transport_prices',
                'endpoint' => 'Eliminar precio fijo de traslado',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al eliminar precio fijo de traslado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'client_place_transport_prices',
                'endpoint' => 'Eliminar precio fijo de traslado',
            ]);
        }
    }
}
