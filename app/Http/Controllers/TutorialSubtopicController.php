<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\TutorialSubtopic;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TutorialSubtopicController extends Controller
{
    // GET / - Listar subtemas (filtrable por módulo)
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $query = TutorialSubtopic::with(['module'])
                ->withCount(['items']);

            if (!$user->is_internal) {
                $query->where('tutorial_subtopics.status', 1)
                      ->whereHas('module', fn($q) => $q->where('status', 1));
            }

            if ($request->has('id_tutorial_module')) {
                $query->where('id_tutorial_module', $request->input('id_tutorial_module'));
            }

            if ($request->has('status')) {
                $query->where('tutorial_subtopics.status', $request->input('status'));
            }

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where('tutorial_subtopics.name', 'like', '%' . $search . '%');
            }

            $subtopics = $query->orderBy('tutorial_subtopics.order')->orderBy('tutorial_subtopics.id')->get();

            return ApiResponse::create('Subtemas traídos correctamente', 200, $subtopics, [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Obtener subtemas de tutorial',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al obtener los subtemas', 500, ['error' => $e->getMessage()], [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Obtener subtemas de tutorial',
            ]);
        }
    }

    // GET /{id} - Obtener un subtema con sus items
    public function show($id, Request $request)
    {
        try {
            $user = auth()->user();
            $subtopic = TutorialSubtopic::with(['module', 'items'])->find($id);

            if (!$subtopic) {
                return ApiResponse::create('Subtema no encontrado', 404, [], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Obtener subtema de tutorial',
                ]);
            }

            if (!$user->is_internal && $subtopic->status !== 1) {
                return ApiResponse::create('Subtema no encontrado', 404, [], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Obtener subtema de tutorial',
                ]);
            }

            return ApiResponse::create('Subtema traído correctamente', 200, $subtopic, [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Obtener subtema de tutorial',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al obtener el subtema', 500, ['error' => $e->getMessage()], [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Obtener subtema de tutorial',
            ]);
        }
    }

    // POST / - Crear subtema (solo admin)
    public function store(Request $request)
    {
        try {
            if (!auth()->user()->is_internal) {
                return ApiResponse::create('No autorizado', 403, [], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Crear subtema de tutorial',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'id_tutorial_module' => 'required|exists:tutorial_modules,id',
                'name'               => 'required|string|max:255',
                'description'        => 'nullable|string',
                'order'              => 'nullable|integer|min:0',
                'status'             => 'nullable|integer|in:1,2',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Crear subtema de tutorial',
                ]);
            }

            $data = $validator->validated();
            $data['order']  = $data['order']  ?? 0;
            $data['status'] = $data['status'] ?? 1;

            $subtopic = TutorialSubtopic::create($data);
            $subtopic->load('module');

            return ApiResponse::create('Subtema creado correctamente', 200, $subtopic, [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Crear subtema de tutorial',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al crear el subtema', 500, ['error' => $e->getMessage()], [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Crear subtema de tutorial',
            ]);
        }
    }

    // PUT /{id} - Actualizar subtema (solo admin)
    public function update(Request $request, $id)
    {
        try {
            if (!auth()->user()->is_internal) {
                return ApiResponse::create('No autorizado', 403, [], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Actualizar subtema de tutorial',
                ]);
            }

            $subtopic = TutorialSubtopic::find($id);
            if (!$subtopic) {
                return ApiResponse::create('Subtema no encontrado', 404, [], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Actualizar subtema de tutorial',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'id_tutorial_module' => 'sometimes|exists:tutorial_modules,id',
                'name'               => 'sometimes|string|max:255',
                'description'        => 'nullable|string',
                'order'              => 'nullable|integer|min:0',
                'status'             => 'sometimes|integer|in:1,2',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Actualizar subtema de tutorial',
                ]);
            }

            $subtopic->update($validator->validated());
            $subtopic->load('module');

            return ApiResponse::create('Subtema actualizado correctamente', 200, $subtopic, [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Actualizar subtema de tutorial',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al actualizar el subtema', 500, ['error' => $e->getMessage()], [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Actualizar subtema de tutorial',
            ]);
        }
    }

    // DELETE /{id} - Eliminar subtema (solo admin)
    public function destroy(Request $request, $id)
    {
        try {
            if (!auth()->user()->is_internal) {
                return ApiResponse::create('No autorizado', 403, [], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Eliminar subtema de tutorial',
                ]);
            }

            $subtopic = TutorialSubtopic::find($id);
            if (!$subtopic) {
                return ApiResponse::create('Subtema no encontrado', 404, [], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Eliminar subtema de tutorial',
                ]);
            }

            $subtopic->delete();

            return ApiResponse::create('Subtema eliminado correctamente', 200, [], [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Eliminar subtema de tutorial',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al eliminar el subtema', 500, ['error' => $e->getMessage()], [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Eliminar subtema de tutorial',
            ]);
        }
    }
}
