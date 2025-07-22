<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Client;
use App\Models\ClientsClasses;
use App\Models\ClientsContact;
use Illuminate\Http\Request;
use App\Models\ClientsType;
use Illuminate\Support\Facades\Validator;

class ClientController extends Controller
{

    public function index(Request $request)
    {
        try {
            // Obtén la página y el número de resultados por página desde la query string con valores por defecto
            $perPage = $request->query('per_page');
            $page = $request->query('page', 1);

            // Construir la consulta base con relaciones
            $query = Client::with(['clientType.status', 'clientClass.status', 'contacts', 'status']);

            // Aplicar filtros si se envían
            if ($request->has('client_class')) {
                $query->where('id_client_class', $request->input('client_class'));
            }
            if ($request->has('client_type')) {
                $query->where('id_client_type', $request->input('client_type'));
            }
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where('name', 'like', '%' . $search . '%');
            }

            // Aplicar paginación con los filtros
            if ($perPage) {
                $clients = $query->paginate($perPage, ['*'], 'page', $page);
                $data = $clients->items();
                $meta_data = [
                    'page' => $clients->currentPage(),
                    'per_page' => $clients->perPage(),
                    'total' => $clients->total(),
                    'last_page' => $clients->lastPage(),
                ];
            } else {
                // Si no se especifica per_page, traer todos los registros sin paginación
                $data = $query->get();
                $meta_data = [
                    'page' => 1,
                    'per_page' => $data->count(),
                    'total' => $data->count(),
                    'last_page' => 1,
                ];
            }

            return ApiResponse::paginate('Clientes traídos correctamente', 200, $data, $meta_data, [
                'request' => $request,
                'module' => 'client',
                'endpoint' => 'Obtener todos los clientes',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener los clientes', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'client',
                'endpoint' => 'Obtener todos los clientes',
            ]);
        }
    }

    // GET BY ID: Obtener información de un cliente por ID
    public function show($id, Request $request)
    {
        try {
            $client = Client::with(['clientType.status', 'clientClass.status', 'contacts', 'status'])->find($id);

            if (!$client) {
                return ApiResponse::create('Cliente no encontrado', 404, ['error' => 'Client not found'], [
                    'request' => $request,
                    'module' => 'client',
                    'endpoint' => 'Obtener un cliente',
                ]);
            }

            return ApiResponse::create('Cliente traido correctamente', 200, $client, [
                'request' => $request,
                'module' => 'client',
                'endpoint' => 'Obtener un cliente',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener el cliente', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'client',
                'endpoint' => 'Obtener un cliente',
            ]);
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
                'company' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:15',
                'address' => 'nullable|string|max:255',
                'cuit' => 'nullable|string|max:12',
                'bonus_percentage' => 'nullable|string|max:255',
                'status' => 'nullable|in:1,2,3',
                'contacts' => 'required|array',
                'contacts.*.name' => 'required|string|max:100',
                'contacts.*.lastname' => 'required|string|max:100',
                'contacts.*.mail' => 'required|email',
                'contacts.*.phone' => 'nullable|string|max:15',
            ]);

            // Verificar si la validación falla
            if ($validator->fails()) {
                return ApiResponse::create('Errores de validación', 422, $validator->errors(), [
                    'request' => $request,
                    'module' => 'client',
                    'endpoint' => 'Crear un cliente',
                ]);
            }

            // Obtener los datos validados
            $validated = $validator->validated();

            // Asignar estado predeterminado si no está definido
            $validated['status'] = $validated['status'] ?? 1;

            // Crear el cliente
            $client = Client::create([
                'id_client_type' => $validated['id_client_type'],
                'id_client_class' => $validated['id_client_class'],
                'name' => $validated['name'],
                'lastname' => $validated['lastname'] ?? null,
                'mail' => $validated['mail'],
                'company' => $validated['company'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'cuit' => $validated['cuit'] ?? null,
                'bonus_percentage' => $validated['bonus_percentage'] ?? null,
                'status' => $validated['status'],
            ]);

            // Crear los contactos asociados
            foreach ($validated['contacts'] as $contact) {
                $client->contacts()->create([
                    'name' => $contact['name'],
                    'lastname' => $contact['lastname'],
                    'mail' => $contact['mail'],
                    'phone' => $contact['phone'] ?? null,
                ]);
            }

            // Cargar relaciones necesarias
            $client = Client::with([
                'clientType.status',
                'clientClass.status',
                'contacts',
                'status'
            ])->find($client->id);

            // Responder con éxito
            return ApiResponse::create('Cliente creado correctamente', 201, $client, [
                'request' => $request,
                'module' => 'client',
                'endpoint' => 'Crear un cliente',
            ]);
        } catch (\Exception $e) {
            // Manejo de errores generales
            return ApiResponse::create('Error al crear un cliente', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'client',
                'endpoint' => 'Crear un cliente',
            ]);
        }
    }

    // PUT: actualizar un cliente
    public function update(Request $request, $id)
    {
        try {
            // Validar datos de entrada
            $validator = Validator::make($request->all(), [
                'id_client_type' => 'required|integer|exists:clients_types,id',
                'id_client_class' => 'required|integer|exists:clients_classes,id',
                'name' => 'required|string|max:255',
                'lastname' => 'nullable|string|max:255',
                'mail' => "required|email|unique:clients,mail,{$id}",
                'company' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:15',
                'address' => 'nullable|string|max:255',
                'cuit' => 'nullable|string|max:12',
                'bonus_percentage' => 'nullable|string|max:255',
                'status' => 'nullable|in:1,2,3',
                'contacts' => 'required|array',
                'contacts.*.id' => 'nullable|integer|exists:clients_contacts,id',
                'contacts.*.name' => 'required|string|max:100',
                'contacts.*.lastname' => 'required|string|max:100',
                'contacts.*.mail' => 'required|email',
                'contacts.*.phone' => 'nullable|string|max:15',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Errores de validación', 422, $validator->errors(), [
                    'request' => $request,
                    'module' => 'client',
                    'endpoint' => 'Actualizar un cliente',
                ]);
            }

            $validated = $validator->validated();

            // Buscar cliente
            $client = Client::findOrFail($id);

            // Actualizar cliente
            $client->update([
                'id_client_type' => $validated['id_client_type'],
                'id_client_class' => $validated['id_client_class'],
                'name' => $validated['name'],
                'lastname' => $validated['lastname'] ?? null,
                'mail' => $validated['mail'],
                'company' => $validated['company'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'cuit' => $validated['cuit'] ?? null,
                'bonus_percentage' => $validated['bonus_percentage'] ?? null,
                'status' => $validated['status'] ?? $client->status,
            ]);

            // Manejo de contactos
            $receivedContactIds = collect($validated['contacts'])->pluck('id')->filter()->toArray();
            $existingContactIds = $client->contacts()->pluck('id')->toArray();

            // Identificar contactos a eliminar
            $contactsToDelete = array_diff($existingContactIds, $receivedContactIds);

            // Eliminar contactos que no estén en la solicitud
            if (!empty($contactsToDelete)) {
                ClientsContact::whereIn('id', $contactsToDelete)->delete();
            }

            // Crear o actualizar contactos
            foreach ($validated['contacts'] as $contactData) {
                if (isset($contactData['id'])) {
                    // Actualizar contacto existente
                    $contact = ClientsContact::find($contactData['id']);
                    $contact->update([
                        'name' => $contactData['name'],
                        'lastname' => $contactData['lastname'],
                        'mail' => $contactData['mail'],
                        'phone' => $contactData['phone'] ?? null,
                    ]);
                } else {
                    // Crear nuevo contacto
                    $client->contacts()->create([
                        'name' => $contactData['name'],
                        'lastname' => $contactData['lastname'],
                        'mail' => $contactData['mail'],
                        'phone' => $contactData['phone'] ?? null,
                    ]);
                }
            }

            // Cargar las relaciones actualizadas
            $client->load(['clientType.status', 'clientClass.status', 'contacts']);

            // Responder con éxito
            return ApiResponse::create('Cliente actualizado correctamente', 200, $client, [
                'request' => $request,
                'module' => 'client',
                'endpoint' => 'Actualizar un cliente',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar un cliente', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'client',
                'endpoint' => 'Actualizar un cliente',
            ]);
        }
    }

    // GET ALL - Retornar lista de tipos de clientes
    public function getAllClientType(Request $request)
    {
        try {
            // Filtrar por estado si se proporciona el parámetro `status`
            $status = $request->query('status');
            $query = ClientsType::with('status');

            if ($status) {
                $query->where('status', $status);
            }

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where('name', 'like', '%' . $search . '%');
            }

            $clientsTypes = $query->get();

            return ApiResponse::create('Tipos de clientes traidos correctamente', 200, $clientsTypes, [
                'request' => $request,
                'module' => 'client',
                'endpoint' => 'Obtener tipos de clientes',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener los tipos de cliente', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'client',
                'endpoint' => 'Obtener tipos de clientes',
            ]);
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
                return ApiResponse::create('Errores de validación', 422, $validator->errors(), [
                    'request' => $request,
                    'module' => 'client',
                    'endpoint' => 'Crear tipo de cliente',
                ]);
            }

            $clientsType = ClientsType::create([
                'name' => $request->name,
                'status' => $request->status ?? 1,
            ]);

            $clientsType->load('status');

            return ApiResponse::create('Tipo de cliente creado correctamente', 201, $clientsType, [
                'request' => $request,
                'module' => 'client',
                'endpoint' => 'Crear tipo de cliente',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al crear tipo de cliente', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'client',
                'endpoint' => 'Crear tipo de cliente',
            ]);
        }
    }

    // PUT - Actualizar un tipo de cliente
    public function updateClientType(Request $request, $id)
    {
        try {
            $clientsType = ClientsType::find($id);

            if (!$clientsType) {
                return response()->json(['error' => 'Client type not found'], 404, [
                    'request' => $request,
                    'module' => 'client',
                    'endpoint' => 'Actualizar tipo de cliente',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'status' => 'nullable|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Errores de validación', 422, $validator->errors(), [
                    'request' => $request,
                    'module' => 'client',
                    'endpoint' => 'Actualizar tipo de cliente',
                ]);
            }

            $clientsType->update([
                'name' => $request->name,
                'status' => $request->status ?? $clientsType->status,
            ]);

            $clientsType->load('status');

            return ApiResponse::create('Tipo de cliente actualizado correctamente', 201, $clientsType, [
                'request' => $request,
                'module' => 'client',
                'endpoint' => 'Actualizar tipo de cliente',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar tipo de cliente', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'client',
                'endpoint' => 'Actualizar tipo de cliente',
            ]);
        }
    }

    public function getAllClientClasses(Request $request)
    {
        try {
            // Filtrar por estado si se proporciona el parámetro `status`
            $status = $request->query('status');
            $query = ClientsClasses::with('status');

            if ($status) {
                $query->where('status', $status);
            }

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where('name', 'like', '%' . $search . '%');
            }

            $clientsClasses = $query->get();

            return ApiResponse::create('Clases de clientes traidas correctamente', 200, $clientsClasses, [
                'request' => $request,
                'module' => 'client',
                'endpoint' => 'Trear clases de clientes',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener las clases de los clientes', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'client',
                'endpoint' => 'Trear clases de clientes',
            ]);
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
                return ApiResponse::create('Errores de validación', 422, $validator->errors(), [
                    'request' => $request,
                    'module' => 'client',
                    'endpoint' => 'Crear clases de clientes',
                ]);
            }

            $clientsClasses = ClientsClasses::create([
                'name' => $request->name,
                'status' => $request->status ?? 1,
            ]);

            $clientsClasses->load('status');

            return ApiResponse::create('Clase del cliente creada correctamente', 201, $clientsClasses, [
                'request' => $request,
                'module' => 'client',
                'endpoint' => 'Crear clases de clientes',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al crear la clase del cliente', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'client',
                'endpoint' => 'Crear clases de clientes',
            ]);
        }
    }

    // PUT - Actualizar un tipo de cliente
    public function updateClientClasses(Request $request, $id)
    {
        try {
            $clientsClasses = ClientsClasses::find($id);

            if (!$clientsClasses) {
                return ApiResponse::create('Clase del cliente no encontrada', 404, ['error' => 'Client Classes not found'], [
                    'request' => $request,
                    'module' => 'client',
                    'endpoint' => 'Actualizar clases de clientes',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'status' => 'nullable|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Errores de validación', 422, $validator->errors(), [
                    'request' => $request,
                    'module' => 'client',
                    'endpoint' => 'Actualizar clases de clientes',
                ]);
            }

            $clientsClasses->update([
                'name' => $request->name,
                'status' => $request->status ?? $clientsClasses->status,
            ]);

            $clientsClasses->load('status');

            return ApiResponse::create('Clase de cliente editada correctamente', 201, $clientsClasses, [
                'request' => $request,
                'module' => 'client',
                'endpoint' => 'Actualizar clases de clientes',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar la clase del cliente', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'client',
                'endpoint' => 'Actualizar clases de clientes',
            ]);
        }
    }
}
