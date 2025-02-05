<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Transportation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TransportationController extends Controller
{
    // GET ALL - Listar transportes con paginación y filtro por estado
    public function index(Request $request)
    {
        try {
            $perPage = $request->query('per_page', 10);
            $page = $request->query('page', 1);
            $status = $request->query('status');

            $query = Transportation::query();
            if (!is_null($status)) {
                $query->where('status', $status);
            }
            
            $transportations = $query->paginate($perPage, ['*'], 'page', $page);

            $transportations->load(['status']);

            $data = $transportations->items();
            $meta_data = [
                'page' => $transportations->currentPage(),
                'per_page' => $transportations->perPage(),
                'total' => $transportations->total(),
                'last_page' => $transportations->lastPage(),
            ];

            return ApiResponse::paginate('Transportes obtenidos correctamente', 200, $data, $meta_data);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener transportes', 500, ['error' => $e->getMessage()]);
        }
    }

    // GET BY ID - Obtener un transporte por ID
    public function show($id)
    {
        Log::info("Obteniendo información del transporte con ID: $id");

        try {
            $transportation = Transportation::find($id);

            if (!$transportation) {
                return ApiResponse::create('Transporte no encontrado', 404, []);
            }

            $transportation->load(['status']);

            return ApiResponse::create('Transporte obtenido correctamente', 200, $transportation);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener el transporte', 500, ['error' => $e->getMessage()]);
        }
    }

    // POST - Crear un transporte
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'load_volume_up' => 'required|numeric|min:0|max:999999.99',
                'schedule_cost' => 'required|integer|min:0',
                'cost_km' => 'required|integer|min:0',
                'charge_discharge_time' => 'required|integer|min:0|max:9999',
                'minimum_quantity' => 'required|integer|min:0|max:999',
                'pawn_quantity' => 'required|integer|min:0|max:999',
                'status' => 'sometimes|integer|in:1,2,3'
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()]);
            }

            $transportation = Transportation::create($request->all() + ['status' => $request->status ?? 1]);

            $transportation->load(['status']);

            return ApiResponse::create('Transporte creado correctamente', 201, $transportation);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al crear el transporte', 500, ['error' => $e->getMessage()]);
        }
    }

    // PUT - actualizar un transporte
    public function update(Request $request, $id)
    {
        try {
            $transportation = Transportation::find($id);

            if (!$transportation) {
                return ApiResponse::create('Transporte no encontrado', 404, []);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'load_volume_up' => 'sometimes|required|numeric|min:0|max:999999.99',
                'schedule_cost' => 'sometimes|required|integer|min:0',
                'cost_km' => 'sometimes|required|integer|min:0',
                'charge_discharge_time' => 'sometimes|required|integer|min:0|max:9999',
                'minimum_quantity' => 'sometimes|required|integer|min:0|max:999',
                'pawn_quantity' => 'sometimes|required|integer|min:0|max:999',
                'status' => 'sometimes|integer|in:1,2,3'
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()]);
            }

            $transportation->update($request->all());

            $transportation->load(['status']);

            return ApiResponse::create('Transporte actualizado correctamente', 200, $transportation);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar el transporte', 500, ['error' => $e->getMessage()]);
        }
    }
}
