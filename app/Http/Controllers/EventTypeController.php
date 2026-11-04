<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\EventType;
use Validator;
use Log;

class EventTypeController extends Controller
{
    // GET ALL - Retorna todos los tipos de eventos, filtrando por nombre si se proporciona
    public function index(Request $request)
    {
        try {
            $perPage = $request->query('per_page');
            $page = $request->query('page', 1);

            $query = EventType::with('status');

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where('name', 'like', '%' . $search . '%');
            }

            // Filtro por estado
            if ($request->filled('status')) {
                $query->where('status', $request->query('status'));
            }

            $query->orderBy('name');

            // Aplicar paginación si se especifica per_page
            if ($perPage) {
                $eventTypes = $query->paginate($perPage, ['*'], 'page', $page);
                $data = $eventTypes->items();
                $meta_data = [
                    'page' => $eventTypes->currentPage(),
                    'per_page' => $eventTypes->perPage(),
                    'total' => $eventTypes->total(),
                    'last_page' => $eventTypes->lastPage(),
                ];
            } else {
                $data = $query->get();
                $meta_data = null;
            }

            return ApiResponse::paginate('Tipos de eventos traídos correctamente', 200, $data, $meta_data, [
                'request' => $request,
                'module' => 'event type',
                'endpoint' => 'Traer tipos de eventos',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::exception('Error al traer los tipos de eventos', $e, [
                'request' => $request,
                'module' => 'event type',
                'endpoint' => 'Traer tipos de eventos',
            ]);
        }
    }

    // POST - Crear un nuevo tipo de evento
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:event_types,name',
                'status' => 'nullable|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request' => $request,
                    'module' => 'event type',
                    'endpoint' => 'Crear tipo de evento',
                ]);
            }

            $eventType = EventType::create([
                'name' => $request->name,
                'status' => $request->status ?? 1,
            ]);

            $eventType->load('status');

            return ApiResponse::create('Tipo de evento creado correctamente', 201, $eventType, [
                'request' => $request,
                'module' => 'event type',
                'endpoint' => 'Crear tipo de evento',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::exception('Error al crear el tipo de evento', $e, [
                'request' => $request,
                'module' => 'event type',
                'endpoint' => 'Crear tipo de evento',
            ]);
        }
    }

    // PUT - Actualizar un tipo de evento
    public function update(Request $request, $id)
    {
        try {
            $eventType = EventType::find($id);

            if (!$eventType) {
                return ApiResponse::create('Tipo de evento no encontrado', 404, ['error' => 'Tipo de evento no encontrado'], [
                    'request' => $request,
                    'module' => 'event type',
                    'endpoint' => 'Actualizar tipo de evento',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255|unique:event_types,name,' . $id,
                'status' => 'sometimes|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request' => $request,
                    'module' => 'event type',
                    'endpoint' => 'Actualizar tipo de evento',
                ]);
            }

            $eventType->update($request->only(['name', 'status']));

            $eventType->load('status');

            return ApiResponse::create('Tipo de evento actualizado correctamente', 200, $eventType, [
                'request' => $request,
                'module' => 'event type',
                'endpoint' => 'Actualizar tipo de evento',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::exception('Error al actualizar el tipo de evento', $e, [
                'request' => $request,
                'module' => 'event type',
                'endpoint' => 'Actualizar tipo de evento',
            ]);
        }
    }
}
