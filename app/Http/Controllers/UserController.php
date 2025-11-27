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
            $isPaginated = $request->has('page') || $request->has('per_page');
            $perPage = $request->get('per_page', 30);

            // Construir la consulta base
            $query = User::with(['userType.status', 'client.status', 'theme', 'status']);

            // Aplicar filtros si se envían
            if ($request->has('user_type')) {
                $query->where('id_user_type', $request->input('user_type'));
            }
            if ($request->has('id_client')) {
                $query->where('id_client', $request->input('id_client'));
            }
            if ($request->has('is_internal')) {
                $query->where('is_internal', filter_var($request->input('is_internal'), FILTER_VALIDATE_BOOLEAN));
            }
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            // Filtro de búsqueda por nombre o email
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            }

            if ($isPaginated) {
                $users = $query->paginate($perPage);
                $data = collect($users->items())->map(function ($user) {
                    return $user->makeHidden(['password']);
                });
                $meta_data = [
                    'page' => $users->currentPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'last_page' => $users->lastPage(),
                ];
            } else {
                $users = $query->get();
                $data = $users->map(function ($user) {
                    return $user->makeHidden(['password']);
                });
                $meta_data = null;
            }

            return ApiResponse::paginate('Usuarios traídos correctamente', 200, $data, $meta_data, [
                'request' => $request,
                'module' => 'user',
                'endpoint' => 'Obtener usuarios',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al obtener los usuarios', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'user',
                'endpoint' => 'Obtener usuarios',
            ]);
        }
    }


    // GET BY ID - Retornar toda la información de un usuario según su id
    public function show($id, Request $request)
    {
        try {
            $user = User::with(['userType.status', 'client.status', 'theme', 'status'])->find($id);

            $user->makeHidden(['password']);

            if (!$user) {
                return ApiResponse::create('Usuario no encontrado', 500, [
                    'request' => $request,
                    'module' => 'user',
                    'endpoint' => 'Obtener un usuario',
                ]);
            }

            return ApiResponse::create('Usuario traido correctamente', 201, $user, [
                'request' => $request,
                'module' => 'user',
                'endpoint' => 'Obtener un usuario',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al obtener un usuario', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'user',
                'endpoint' => 'Obtener un usuario',
            ]);
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
                'phone' => 'nullable|string|max:15',
                'is_internal' => 'nullable|in:0,1',
                'id_client' => 'required_if:is_internal,0|exists:clients,id', // Validación condicional
                'permissions' => 'sometimes|json',
                'theme' => 'sometimes|integer',
            ]);

            // Verifica si la validación falla
            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request' => $request,
                    'module' => 'user',
                    'endpoint' => 'Crear un usuario',
                ]);
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
            return ApiResponse::create('Usuario creado correctamente', 200, $user, [
                'request' => $request,
                'module' => 'user',
                'endpoint' => 'Crear un usuario',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al crear un usuario', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'user',
                'endpoint' => 'Crear un usuario',
            ]);
        }
    }


    // PUT - actualizar un usuario
    public function update(Request $request, $id)
    {
        try {
            // Busca al usuario por su ID
            $user = User::find($id);

            if (!$user) {
                return ApiResponse::create('Usuario no encontrado', 404, [], [
                    'request' => $request,
                    'module' => 'user',
                    'endpoint' => 'Actualizar un usuario',
                ]);
            }

            // Realiza la validación de los datos
            $validator = Validator::make($request->all(), [
                'user' => 'sometimes|string|unique:users,user,' . $id . '|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $id . '|max:255',
                'id_user_type' => 'sometimes|exists:user_types,id',
                'name' => 'sometimes|string|max:255',
                'lastname' => 'sometimes|string|max:255',
                'phone' => 'nullable|string|max:15',
                'is_internal' => 'nullable|in:0,1',
                'id_client' => 'required_if:is_internal,0|exists:clients,id',
                'new_password' => 'sometimes|string|min:8|confirmed',
                'permissions' => 'sometimes|json',
                'theme' => 'sometimes|integer',
                'status' => 'sometimes|integer|in:1,2,3',
            ]);

            // Verifica si la validación falla
            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request' => $request,
                    'module' => 'user',
                    'endpoint' => 'Actualizar un usuario',
                ]);
            }

            $data = $validator->validated();
            if (isset($data['new_password']) && !empty($data['new_password'])) {

                // Si se envía una nueva contraseña, la hasheamos antes de actualizarla
                $data['password'] = Hash::make($data['new_password']);
                unset($data['new_password']); // Eliminamos el campo new_password del array de datos
            } else {
                // Si no se envía una nueva contraseña, mantenemos la actual
                unset($data['password']);
            }

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

            // Oculta la contraseña del usuario antes de devolverlo
            $user->makeHidden(['password']);

            return ApiResponse::create('Usuario actualizado correctamente', 200, $user, [
                'request' => $request,
                'module' => 'user',
                'endpoint' => 'Actualizar un usuario',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al actualizar el usuario', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'user',
                'endpoint' => 'Actualizar un usuario',
            ]);
        }
    }

    public function updateOwn(Request $request)
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return ApiResponse::create('Usuario no encontrado', 404, [], [
                    'request' => $request,
                    'module' => 'user',
                    'endpoint' => 'Actualizar usuario propio',
                ]);
            }

            // Validación de los datos
            $validator = Validator::make($request->all(), [
                'user' => 'sometimes|string|unique:users,user,' . $user->id . '|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $user->id . '|max:255',
                'password' => 'sometimes|string|min:8|confirmed', // Agregamos 'confirmed' para validar repetición
                'name' => 'sometimes|string|max:255',
                'phone' => 'nullable|string|max:15',
                'lastname' => 'sometimes|string|max:255',
                'theme' => 'sometimes|integer'
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request' => $request,
                    'module' => 'user',
                    'endpoint' => 'Actualizar usuario propio',
                ]);
            }

            $data = $validator->validated();

            // Si el usuario envía una nueva contraseña, la hasheamos antes de actualizarla
            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            // Mantener el valor actual del tema si no se envía uno nuevo
            if (!isset($data['theme'])) {
                $data['theme'] = $user->theme;
            }

            $user->update($data);

            $user->load('userType.status', 'client.status', 'theme', 'status');

            return ApiResponse::create('Usuario actualizado correctamente', 200, $user, [
                'request' => $request,
                'module' => 'user',
                'endpoint' => 'Actualizar usuario propio',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al actualizar el usuario', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'user',
                'endpoint' => 'Actualizar usuario propio',
            ]);
        }
    }
}
