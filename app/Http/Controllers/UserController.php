<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Models\UserType;
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
        try {
            // Verificar si se envían los parámetros `page` o `per_page`
            $page = $request->has('page');
            $perPage = $request->get('per_page', 10); // Default: 10 resultados por página

            // Obtener los usuarios
            if ($page) {
                // Realizar paginación si se envían los parámetros
                $users = User::with(['userType.status', 'client.status', 'theme', 'status'])
                    ->paginate($perPage);

                // Construir datos de respuesta para paginación
                $data = $users->items();
                $meta_data = [
                    'page' => $users->currentPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'last_page' => $users->lastPage(),
                ];

                return ApiResponse::paginate('Usuarios traídos correctamente', 200, $data, $meta_data);
            } else {
                // Devolver todos los resultados si no se envían los parámetros
                $users = User::with(['userType.status', 'client.status', 'theme', 'status'])
                    ->get()
                    ->makeHidden(['password']);

                return ApiResponse::paginate('Usuarios traídos correctamente', 200, $users, null);
            }
        } catch (Exception $e) {
            return ApiResponse::create('Error al traer los usuarios', 500, ['error' => $e->getMessage()]);
        }
    }

    // GET BY ID - Retornar toda la información de un usuario según su id
    public function show($id)
    {
        try {
            $user = User::with(['userType.status', 'client.status', 'theme', 'status'])->find($id);

            if (!$user) {
                return ApiResponse::create('Usuario no encontrado', 500);
            }

            return ApiResponse::create('Usuario traido correctamente', 201, $user);
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
                return ApiResponse::create('Error de validacion', 422, $validator->errors());
            }

            $data = $validator->validated();
            // Encripta la contraseña y define el estado por defecto
            $data['password'] = Hash::make($data['password']);
            $data['status'] = 1;

            if (empty($data['permissions'])) {
                $data['permissions'] = '{}';
            }

            // Si no se envía theme, asignar el valor por defecto "1"
            if (empty($data['theme'])) {
                $data['theme'] = 1;
            }

            // Crea el usuario

            $user = User::create($data);

            $user->load('userType.status', 'client.status', 'theme', 'status');
            return ApiResponse::create('Usuario creado correctamente', 200, $user);
        } catch (Exception $e) {
            return ApiResponse::create('Error al crear un usuario', 500, ['error' => $e->getMessage()]);
        }
    }

    // PUT - Editar un usuario
    public function update(Request $request, $id)
    {
        try {
            // Busca al usuario por su ID
            $user = User::find($id);

            if (!$user) {
                return ApiResponse::create('Usuario no encontrado', 404, []);
            }

            // Realiza la validación de los datos
            $validator = Validator::make($request->all(), [
                'user' => 'sometimes|string|unique:users,user,' . $id . '|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $id . '|max:255',
                'id_user_type' => 'sometimes|exists:user_types,id',
                'name' => 'sometimes|string|max:255',
                'lastname' => 'sometimes|string|max:255',
                // Validación opcional para permissions y theme
                'permissions' => 'sometimes|json',
                'theme' => 'sometimes|integer',
                'status' => 'sometimes|string|in:activo,inactivo',
            ]);

            // Verifica si la validación falla
            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, $validator->errors());
            }

            $data = $validator->validated();

            // Si no se envía permissions o theme, usa los valores actuales del usuario
            if (!isset($data['permissions'])) {
                $data['permissions'] = $user->permissions;
            }

            if (!isset($data['theme'])) {
                $data['theme'] = $user->theme;
            }

            // Actualiza el usuario con los datos validados
            $user->update($data);

            $user->load('userType.status', 'client.status', 'theme', 'status');

            return ApiResponse::create('Usuario actualizado correctamente', 200, $user);
        } catch (Exception $e) {
            return ApiResponse::create('Error al actualizar el usuario', 500, ['error' => $e->getMessage()]);
        }
    }

    public function getAllUserType(Request $request)
    {
        try {
            $status = $request->query('status'); // Parámetro opcional

            $query = UserType::with('status'); // Aplica la relación con antelación

            if ($status) {
                $query->where('status', $status);
            }

            $userTypes = $query->get(); // Ejecuta la consulta y obtiene los resultados

            return ApiResponse::create('Tipo de usuarios traidos correctamente', 200, $userTypes);
        } catch (Exception $e) {
            return ApiResponse::create('Error al traer tipos de usuarios', 500, ['error' => $e->getMessage()]);
        }
    }


    public function storeUserType(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|unique:user_types,name|max:255',
                'permissions' => 'nullable',
                'status' => 'nullable|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }

            $userType = UserType::create([
                'name' => $request->name,
                'permissions' => $request->permissions ?? '{}',
                'status' => $request->status ?? 1,
            ]);

            $userType->load('status');

            return ApiResponse::create('Tipo de usuario creado correctamente', 201, $userType);
        } catch (Exception $e) {
            return ApiResponse::create('Error al crear un tipo de usuario', 500, ['error' => $e->getMessage()]);
        }
    }

    // PUT: Actualizar un tipo de usuario
    public function updateUserType(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|unique:user_types,name|max:255',
                'permissions' => 'nullable',
                'status' => 'nullable|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }

            $userType = UserType::findOrFail($id);

            $userType->update([
                'name' => $request->name,
                'permissions' => $request->permissions ?? $userType->permissions,
                'status' => $request->status ?? $userType->status,
            ]);

            $userType->load('status');

            return ApiResponse::create('Tipo de usuario editado correctamente', 201, $userType);
        } catch (Exception $e) {
            return ApiResponse::create('Error al editar un tipo de usuario', 500, ['error' => $e->getMessage()]);
        }
    }
}
