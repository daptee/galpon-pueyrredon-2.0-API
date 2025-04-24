<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Budget;
use App\Models\BudgetProducts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BudgetController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->query('per_page', 10);
            $page = $request->query('page', 1);

            $query = Budget::with(['client', 'place', 'budgetStatus']);

            // Filtros dinámicos
            if ($request->has('place')) {
                $query->where('id_place', $request->input('place'));
            }
            if ($request->has('status')) {
                $query->where('id_budget_status', $request->input('status'));
            }
            if ($request->has('client')) {
                $query->where('id_client', $request->input('client'));
            }
            if ($request->has('event_date')) {
                $query->whereDate('date_event', $request->input('event_date'));
            }
            if ($request->has('start_date')) {
                $query->whereDate('date_event', '>=', $request->input('start_date'));
            }

            $budgets = $query->paginate($perPage, ['*'], 'page', $page);

            $data = $budgets->items();
            $meta_data = [
                'page' => $budgets->currentPage(),
                'per_page' => $budgets->perPage(),
                'total' => $budgets->total(),
                'last_page' => $budgets->lastPage(),
            ];

            return ApiResponse::paginate('Presupuestos obtenidos correctamente', 200, $data, $meta_data, [
                'request' => $request,
                'module' => 'budget',
                'endpoint' => 'Obtener todos los presupuestos',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener los presupuestos', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'budget',
                'endpoint' => 'Obtener todos los presupuestos',
            ]);
        }
    }

    public function show($id)
    {
        try {
            $budget = Budget::with([
                'budgetStatus',
                /* 'pdfText', */
                'place',
                'transportation',
                'client',
                'budgetProducts.product'
            ])->find($id);

            if (!$budget) {
                return ApiResponse::create('Presupuesto no encontrado', 404, ['error' => 'Presupuesto no encontrado'], []);
            }

            return ApiResponse::create('Presupuesto obtenido correctamente', 200, $budget, []);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener el presupuesto', 500, ['error' => $e->getMessage()], []);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_client' => 'required|integer|exists:clients,id',
                'client_mail' => 'required|email',
                'client_phone' => 'required|string',
                'id_place' => 'required|integer|exists:places,id',
                'id_transportation' => 'required|integer|exists:transportations,id',
                'date_event' => 'required|date',
                'time_event' => 'required|string',
                'days' => 'required|integer|min:1',
                'quoted_days' => 'required|integer|min:1',
                'total_price_products' => 'required|numeric',
                'client_bonification' => 'required|numeric',
                'client_bonification_edited' => 'required|numeric',
                'total_bonification' => 'required|numeric',
                'transportation_cost' => 'required|numeric',
                'transportation_cost_edited' => 'required|numeric',
                'subtotal' => 'required|numeric',
                'iva' => 'required|numeric',
                'total' => 'required|numeric',
                'version_number' => 'required|integer',
                'id_budget_status' => 'required|exists:budget_status,id',
                'products_has_prices' => 'required|boolean',
                'id_budget' => 'nullable|integer|exists:budgets,id',
                'observations' => 'nullable|string',
                'product' => 'required|array|min:1',
                'product.*.id_product' => 'required|integer|exists:products,id',
                'product.*.quantity' => 'required|integer|min:1',
                'product.*.price' => 'required|numeric',
                'product.*.has_stock' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()], [
                    'request' => $request,
                    'module' => 'budget',
                    'endpoint' => 'Crear presupuesto',
                ]);
            }

            $data = $request->all();

            if (!empty($data['id_budget'])) {
                $data['id_budget_parent'] = $data['id_budget'];
            }

            $budget = Budget::create($data);

            foreach ($data['product'] as $item) {
                BudgetProducts::create([
                    'id_budget' => $budget->id,
                    'id_product' => $item['id_product'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'has_stock' => $item['has_stock'],
                ]);
            }

            $budget->load([
                'budgetStatus',
                /* 'products',
                'pdfText', */
                'place',
                'transportation',
                'client',
                'budgetProducts.product'
            ]);

            // generar PDF y enviar email

            return ApiResponse::create('Presupuesto creado correctamente', 201, $budget, [
                'request' => $request,
                'module' => 'budget',
                'endpoint' => 'Crear presupuesto',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al crear el presupuesto', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'budget',
                'endpoint' => 'Crear presupuesto',
            ]);
        }
    }

    public function updateObservations(Request $request, $id)
    {
        try {
            $budget = Budget::find($id);

            if (!$budget) {
                return ApiResponse::create('Presupuesto no encontrado', 404, ['error' => 'Presupuesto no encontrado'], []);
            }

            $validator = Validator::make($request->all(), [
                'observations' => 'required|string'
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()], []);
            }

            $budget->observations = $request->observations;
            $budget->save();

            return ApiResponse::create('Observaciones actualizadas correctamente', 201, $budget, []);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar observaciones', 500, ['error' => $e->getMessage()], []);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $budget = Budget::find($id);

            if (!$budget) {
                return ApiResponse::create('Presupuesto no encontrado', 404, ['error' => 'Presupuesto no encontrado'], []);
            }

            $validator = Validator::make($request->all(), [
                'id_budget_status' => 'required|exists:budget_status,id'
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()], []);
            }

            $budget->id_budget_status = $request->id_budget_status;
            $budget->save();

            $budget->load([
                'budgetStatus'
            ]);

            return ApiResponse::create('Estado actualizado correctamente', 201, $budget, []);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar el estado', 500, ['error' => $e->getMessage()], []);
        }
    }

    public function updateContact(Request $request, $id)
    {
        try {
            $budget = Budget::find($id);

            if (!$budget) {
                return ApiResponse::create('Presupuesto no encontrado', 404, ['error' => 'Presupuesto no encontrado'], []);
            }

            $validator = Validator::make($request->all(), [
                'client_mail' => 'required|email',
                'client_phone' => 'required|string',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()], []);
            }

            $budget->client_mail = $request->client_mail;
            $budget->client_phone = $request->client_phone;
            $budget->save();

            $budget->load([
                'client'
            ]);

            return ApiResponse::create('Contacto actualizado correctamente', 201, $budget, []);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar el contacto', 500, ['error' => $e->getMessage()], []);
        }
    }

    public function generatePdf($id)
    {
        try {
            // TODO: lógica para generar PDF

            return ApiResponse::create('PDF generado correctamente', 200, ['pdf_path' => 'ruta/del/pdf.pdf'], []);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al generar el PDF', 500, ['error' => $e->getMessage()], []);
        }
    }

    public function resendEmail($id)
    {
        try {
            // TODO: lógica para reenviar email

            return ApiResponse::create('Email reenviado correctamente', 200, [], []);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al reenviar el email', 500, ['error' => $e->getMessage()], []);
        }
    }

}
