<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Client;
use App\Models\ClientsClasses;
use Illuminate\Http\Request;
use App\Models\ClientsType;
use Illuminate\Support\Facades\Validator;

class ClientController extends Controller
{

    public function index(Request $request)
    {
        try {// Obtén la página y el número de resultados por página desde la query string con valores por defecto
            $perPage = $request->query('per_page', 10);
            $page = $request->query('page', 1);

            // Paginación personalizada usando los parámetros
            $clients = Client::with(['clientType.status', 'clientClass.status', 'status'])
                ->paginate($perPage, ['*'], 'page', $page);

            $data = $clients->items();
            $meta_data = [
                'page' => $clients->currentPage(),
                'per_page' => $clients->perPage(),
                'total' => $clients->total(),
                'last_page' => $clients->lastPage(),
            ];

            return ApiResponse::paginate('Clientes traidos correctamente', 201, $data, $meta_data);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al traer los clientes', 500, ['error' => $e->getMessage()]);
        }
    }


    // GET BY ID: Obtener información de un cliente por ID
    public function show($id)
    {
        try {
            $client = Client::with(['clientType.status', 'clientClass.status', 'status'])->find($id);

            if (!$client) {
                return ApiResponse::create('Cliente no encontrado', 404, ['error' => 'Client not found']);
            }

            return ApiResponse::create('Cliente traido correctamente', 200, $client);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al traer el cliente', 500, ['error' => $e->getMessage()]);
        }
    }

    // POST: Crear un cliente
    public function store(Request $request)
    {
        try {
            // Crear el validador
            $validator = Validator::make($request->all(), [
                'id_client_type' => 'required|integer|exists:clients_types,id',
                'id_client_class' => 'required|integer|exists:clients_classes,id',
                'name' => 'required|string|max:255',
                'lastname' => 'nullable|string|max:255',
                'mail' => 'required|email|unique:clients,mail',
                'status' => 'nullable|in:1,2,3',
            ]);

            // Verificar si la validación falla
            if ($validator->fails()) {
                return ApiResponse::create('Errores de validación', 422, $validator->errors());
            }

            // Obtener los datos validados
            $validated = $validator->validated();

            // Asignar estado predeterminado si no está definido
            $validated['status'] = $validated['status'] ?? 1;

            // Crear el cliente
            $client = Client::create($validated);

            $client->load([
                'clientType.status',
                'clientClass.status',
                'status'
            ]);

            // Responder con éxito
            return ApiResponse::create('Cliente creado correctamente', 201, $client);
        } catch (\Exception $e) {
            // Manejo de errores generales
            return ApiResponse::create('Error al crear un cliente', 500, ['error' => $e->getMessage()]);
        }
    }


    // PUT: Editar un cliente
    public function update(Request $request, $id)
    {
        try {
            // Crear el validador
            $validator = Validator::make($request->all(), [
                'id_client_type' => 'required|integer|exists:clients_types,id',
                'id_client_class' => 'required|integer|exists:clients_classes,id',
                'name' => 'required|string|max:255',
                'lastname' => 'nullable|string|max:255',
                'mail' => 'required|email|unique:clients,mail,' . $id, // Ignorar el correo del cliente actual
                'status' => 'nullable|in:1,2,3',
            ]);

            // Verificar si la validación falla
            if ($validator->fails()) {
                return ApiResponse::create('Errores de validación', 422, $validator->errors());
            }

            // Obtener los datos validados
            $validated = $validator->validated();

            // Buscar el cliente
            $client = Client::findOrFail($id);

            // Actualizar el cliente
            $client->update($validated);

            // Cargar relaciones anidadas
            $client->load([
                'clientType.status',
                'clientClass.status',
                'status'
            ]);

            // Responder con éxito
            return ApiResponse::create('Cliente actualizado correctamente', 200, $client);
        } catch (\Exception $e) {
            // Manejo de errores generales
            return ApiResponse::create('Error al actualizar el cliente', 500, ['error' => $e->getMessage()]);
        }
    }

    // GET ALL - Retornar lista de tipos de clientes
    public function getAllClientType(Request $request)
    {
        try {
            // Filtrar por estado si se proporciona el parámetro `status`
            $status = $request->query('status');
            $clientsTypesQuery = ClientsType::with('status');

            if ($status) {
                $clientsTypesQuery->where('status', $status);
            }

            $clientsTypes = $clientsTypesQuery->get();

            return ApiResponse::create('Tipos de clientes traidos correctamente', 200, $clientsTypes);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al traer los tipos de cliente', 500, ['error' => $e->getMessage()]);
        }
    }

    // POST - Crear un nuevo tipo de cliente
    public function storeClientType(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'status' => 'nullable|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Errores de validación', 422, $validator->errors());
            }

            $clientsType = ClientsType::create([
                'name' => $request->name,
                'status' => $request->status ?? 1,
            ]);

            $clientsType->load('status');

            return ApiResponse::create('Tipo de cliente creado correctamente', 201, $clientsType);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al crear tipo de cliente', 500, ['error' => $e->getMessage()]);
        }
    }

    // PUT - Actualizar un tipo de cliente
    public function updateClientType(Request $request, $id)
    {
        try {
            $clientsType = ClientsType::find($id);

            if (!$clientsType) {
                return response()->json(['error' => 'Client type not found'], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'status' => 'nullable|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Errores de validación', 422, $validator->errors());
            }

            $clientsType->update([
                'name' => $request->name,
                'status' => $request->status ?? $clientsType->status,
            ]);

            $clientsType->load('status');

            return ApiResponse::create('Tipo de cliente editado correctamente', 201, $clientsType);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al editar tipo de cliente', 500, ['error' => $e->getMessage()]);
        }
    }

    public function getAllClientClasses(Request $request)
    {
        try {
            // Filtrar por estado si se proporciona el parámetro `status`
            $status = $request->query('status');
            $clientsClassesQuery = ClientsClasses::with('status');

            if ($status) {
                $clientsClassesQuery->where('status', $status);
            }

            $clientsClasses = $clientsClassesQuery->get();

            return ApiResponse::create('Clases de clientes traidas correctamente', 200, $clientsClasses);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al traer las clases de los clientes', 500, ['error' => $e->getMessage()]);
        }
    }

    // POST - Crear un nuevo tipo de cliente
    public function storeClientClasses(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'status' => 'nullable|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Errores de validación', 422, $validator->errors());
            }

            $clientsClasses = ClientsClasses::create([
                'name' => $request->name,
                'status' => $request->status ?? 1,
            ]);

            $clientsClasses->load('status');

            return ApiResponse::create('Clase del cliente creada correctamente', 201, $clientsClasses);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al crear la clase del cliente', 500, ['error' => $e->getMessage()]);
        }
    }

    // PUT - Actualizar un tipo de cliente
    public function updateClientClasses(Request $request, $id)
    {
        try {
            $clientsClasses = ClientsClasses::find($id);

            if (!$clientsClasses) {
                return response()->json(['error' => 'Client Classes not found'], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'status' => 'nullable|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Errores de validación', 422, $validator->errors());
            }

            $clientsClasses->update([
                'name' => $request->name,
                'status' => $request->status ?? $clientsClasses->status,
            ]);

            $clientsClasses->load('status');

            return ApiResponse::create('Clase de cliente editada correctamente', 201, $clientsClasses);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al editar la clase del cliente', 500, ['error' => $e->getMessage()]);
        }
    }
}
