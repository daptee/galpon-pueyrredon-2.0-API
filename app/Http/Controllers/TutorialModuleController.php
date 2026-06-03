<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\TutorialModule;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TutorialModuleController extends Controller
{
    // GET / - Listar módulos (admin ve todos, cliente solo los activos)
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $query = TutorialModule::withCount(['subtopics']);

            if (!$user->is_internal) {
                $query->where('status', 1);
            }

            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where('name', 'like', '%' . $search . '%');
            }

            $modules = $query->orderBy('order')->orderBy('id')->get();

            return ApiResponse::create('Módulos traídos correctamente', 200, $modules, [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Obtener módulos de tutorial',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al obtener los módulos', 500, ['error' => $e->getMessage()], [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Obtener módulos de tutorial',
            ]);
        }
    }

    // GET /{id} - Obtener un módulo con sus subtemas
    public function show($id, Request $request)
    {
        try {
            $user = auth()->user();
            $module = TutorialModule::with(['subtopics'])->find($id);

            if (!$module) {
                return ApiResponse::create('Módulo no encontrado', 404, [], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Obtener módulo de tutorial',
                ]);
            }

            if (!$user->is_internal && $module->status !== 1) {
                return ApiResponse::create('Módulo no encontrado', 404, [], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Obtener módulo de tutorial',
                ]);
            }

            return ApiResponse::create('Módulo traído correctamente', 200, $module, [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Obtener módulo de tutorial',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al obtener el módulo', 500, ['error' => $e->getMessage()], [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Obtener módulo de tutorial',
            ]);
        }
    }

    // POST / - Crear módulo (solo admin)
    public function store(Request $request)
    {
        try {
            if (!auth()->user()->is_internal) {
                return ApiResponse::create('No autorizado', 403, [], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Crear módulo de tutorial',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'name'        => 'required|string|max:255',
                'description' => 'nullable|string',
                'order'       => 'nullable|integer|min:0',
                'status'      => 'nullable|integer|in:1,2',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Crear módulo de tutorial',
                ]);
            }

            $data = $validator->validated();
            $data['order']  = $data['order']  ?? 0;
            $data['status'] = $data['status'] ?? 1;

            $module = TutorialModule::create($data);

            return ApiResponse::create('Módulo creado correctamente', 200, $module, [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Crear módulo de tutorial',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al crear el módulo', 500, ['error' => $e->getMessage()], [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Crear módulo de tutorial',
            ]);
        }
    }

    // PUT /{id} - Actualizar módulo (solo admin)
    public function update(Request $request, $id)
    {
        try {
            if (!auth()->user()->is_internal) {
                return ApiResponse::create('No autorizado', 403, [], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Actualizar módulo de tutorial',
                ]);
            }

            $module = TutorialModule::find($id);
            if (!$module) {
                return ApiResponse::create('Módulo no encontrado', 404, [], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Actualizar módulo de tutorial',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'name'        => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'order'       => 'nullable|integer|min:0',
                'status'      => 'sometimes|integer|in:1,2',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Actualizar módulo de tutorial',
                ]);
            }

            $module->update($validator->validated());

            return ApiResponse::create('Módulo actualizado correctamente', 200, $module, [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Actualizar módulo de tutorial',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al actualizar el módulo', 500, ['error' => $e->getMessage()], [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Actualizar módulo de tutorial',
            ]);
        }
    }

    // DELETE /{id} - Eliminar módulo (solo admin)
    public function destroy(Request $request, $id)
    {
        try {
            if (!auth()->user()->is_internal) {
                return ApiResponse::create('No autorizado', 403, [], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Eliminar módulo de tutorial',
                ]);
            }

            $module = TutorialModule::find($id);
            if (!$module) {
                return ApiResponse::create('Módulo no encontrado', 404, [], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Eliminar módulo de tutorial',
                ]);
            }

            $module->delete();

            return ApiResponse::create('Módulo eliminado correctamente', 200, [], [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Eliminar módulo de tutorial',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al eliminar el módulo', 500, ['error' => $e->getMessage()], [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Eliminar módulo de tutorial',
            ]);
        }
    }
}
