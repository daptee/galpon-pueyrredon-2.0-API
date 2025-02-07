<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\UserType;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Log;

class UserTypeController extends Controller
{
    public function index(Request $request)
    {
        try {
            $status = $request->query('status'); // Parámetro opcional

            $query = UserType::with('status'); // Aplica la relación con antelación

            if ($status) {
                $query->where('status', $status);
            }

            $userTypes = $query->get(); // Ejecuta la consulta y obtiene los resultados

            return ApiResponse::create('Tipo de usuarios traidos correctamente', 200, $userTypes, [
                'request' => $request,
                'module' => 'user type',
                'endpoint' => 'Obtener tipos de usuarios',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al obtener tipos de usuarios', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'user type',
                'endpoint' => 'Obtener tipos de usuarios',
            ]);
        }
    }


    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|unique:user_types,name|max:255',
                'permissions' => 'nullable',
                'status' => 'nullable|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, $validator->errors(), [
                    'request' => $request,
                    'module' => 'user type',
                    'endpoint' => 'Crear tipo de usuario',
                ]);
            }

            $userType = UserType::create([
                'name' => $request->name,
                'permissions' => $request->permissions ?? '{}',
                'status' => $request->status ?? 1,
            ]);

            $userType->load('status');

            return ApiResponse::create('Tipo de usuario creado correctamente', 201, $userType, [
                'request' => $request,
                'module' => 'user type',
                'endpoint' => 'Crear tipo de usuario',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al crear un tipo de usuario', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'user type',
                'endpoint' => 'Crear tipo de usuario',
            ]);
        }
    }

    // PUT: Actualizar un tipo de usuario
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|unique:user_types,name|max:255',
                'permissions' => 'nullable',
                'status' => 'nullable|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, $validator->errors(), [                    
                    'request' => $request,
                    'module' => 'user type',
                    'endpoint' => 'Actualizar tipo de usuario',
                ]);
            }

            $userType = UserType::findOrFail($id);

            $userType->update([
                'name' => $request->name,
                'permissions' => $request->permissions ?? $userType->permissions,
                'status' => $request->status ?? $userType->status,
            ]);

            $userType->load('status');

            return ApiResponse::create('Tipo de usuario actualizado correctamente', 201, $userType, [
                'request' => $request,
                'module' => 'user type',
                'endpoint' => 'Actualizar tipo de usuario',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al actualizar un tipo de usuario', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'user type',
                'endpoint' => 'Actualizar tipo de usuario',
            ]);
        }
    }
}
