<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Log;

class UserController extends Controller
{
    // GET ALL - Retornar usuarios con sus datos relacionados, paginados
    public function index(Request $request)
    {
        $users = User::with(['userType', 'client', 'theme'])
            ->paginate($request->get('per_page', 10)); // Paginación con 10 resultados por defecto.

        return response()->json($users);
    }

    // GET BY ID - Retornar toda la información de un usuario según su id
    public function show($id)
    {
        try {
            $user = User::with(['userType', 'client', 'theme'])->find($id);

            if (!$user) {
                return ApiResponse::create('Usuario no encontrado', 500);
            }

            return ApiResponse::create('Succeeded', 201, $user);
        } catch (Exception $e) {
            return ApiResponse::create('Error al traer un usuario', 500, ['error' => $e->getMessage()]);
        }
    }

    // POST - Crear un nuevo usuario
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user' => 'required|string|unique:users,user|max:255',
                'password' => 'required|string|min:8',
                'email' => 'required|email|unique:users,email|max:255',
                'id_user_type' => 'required|exists:user_types,id',
                'name' => 'required|string|max:255',
                'lastname' => 'required|string|max:255',
                // Validación opcional para permissions y theme
                'permissions' => 'sometimes|json',
                'theme' => 'sometimes|integer',
            ]);

            // Verifica si la validación falla
            if ($validator->fails()) {
                return ApiResponse::create('Validation failed', 422, $validator->errors());
            }

            $data = $validator->validated();
            // Encripta la contraseña y define el estado por defecto
            $data['password'] = Hash::make($data['password']);
            $data['status'] = 'activo';

            if (empty($data['permissions'])) {
                $data['permissions'] = '{}';
            }

            // Si no se envía theme, asignar el valor por defecto "1"
            if (empty($data['theme'])) {
                $data['theme'] = 1;
            }

            // Crea el usuario
            $user = User::create($data);
            return ApiResponse::create('Succeeded', 200, $user);
        } catch (Exception $e) {
            return ApiResponse::create('Error al crear un usuario', 500, ['error' => $e->getMessage()]);
        }
    }

    // PUT - Editar un usuario
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $validated = $request->validate([
            'user' => 'sometimes|required|string|unique:users,user,' . $id . '|max:255',
            'password' => 'nullable|string|min:8',
            'email' => 'sometimes|required|email|unique:users,email,' . $id . '|max:255',
            'id_user_type' => 'sometimes|required|exists:user_types,id',
            'name' => 'sometimes|required|string|max:255',
            'lastname' => 'sometimes|required|string|max:255',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json($user);
    }
}
