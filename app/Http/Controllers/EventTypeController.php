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
            $query = EventType::query();

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where('name', 'like', '%' . $search . '%');
            }

            $eventTypes = $query->get();

            $eventTypes->load('status');

            return ApiResponse::create('Tipos de eventos traídos correctamente', 200, $eventTypes, [
                'request' => $request,
                'module' => 'event type',
                'endpoint' => 'Traer tipos de eventos',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al traer los tipos de eventos', 500, ['error' => $e->getMessage()], [
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
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()], [
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
            return ApiResponse::create('Error al crear el tipo de evento', 500, ['error' => $e->getMessage()], [
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
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()], [
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
            return ApiResponse::create('Error al actualizar el tipo de evento', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'event type',
                'endpoint' => 'Actualizar tipo de evento',
            ]);
        }
    }
}
