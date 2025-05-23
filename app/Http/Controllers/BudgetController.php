<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Budget;
use App\Models\BudgetProducts;
use App\Models\Product;
use App\Models\ProductUseStock;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Log;

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

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where('id', 'like', '%' . $search . '%');
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
                'total_bonification' => 'required|string',
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
                'product.*.has_price' => 'required|boolean',
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
                    'has_price' => $item['has_price'],
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

            $pdf = Pdf::loadView('pdf.budget', compact('budget'));

            if (!file_exists(public_path("storage/budgets/"))) {
                mkdir(public_path("storage/budgets/"), 0777, true);
            }

            if (file_exists(public_path('fonts/Lato-Regular.ttf'))) {
                \Log::warning('Fuente encontrada: fonts/Lato-Regular.ttf');
            } else {
                \Log::warning('Fuente no encontrada: fonts/Lato-Regular.ttf');
            }

            $pdf->save(public_path("storage/budgets/budget-{$budget->id}.pdf"));

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
                'budgetStatus',
                'budgetProducts.product'
            ]);

            if ($request->id_budget_status == 3) {
                foreach ($budget->budgetProducts as $item) {
                    if ($item->has_stock) {
                        ProductUseStock::create([
                            'id_budget' => $budget->id,
                            'id_product' => $item->id_product,
                            'id_product_stock' => $item->product->product_stock == null ? $item->id_product : $item->product->product_stock,
                            'date_from' => $budget->date_event,
                            //sumar los dias al evento
                            'date_to' => \Carbon\Carbon::parse($budget->date_event)->addDays($budget->days),
                            'quantity' => $item->quantity
                        ]);
                    }
                }
                ;
            }

            return ApiResponse::create('Estado actualizado correctamente', 201, $budget, []);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar el estado', 500, ['error' => $e->getMessage()], []);
        }
    }

    // Se necesita hacer un endpoint que reciba id_product, cantidad, fecha y cantidad de dias. Verificar en una tabla de control de stock usado, si ese producto, para alguno de los dias y la cantidad, NO tiene stock. En tal caso retornar indicando.
    public function checkStock(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_product' => 'required|integer|exists:products,id',
                'quantity' => 'required|integer|min:1',
                'date_from' => 'required|date',
                'days' => 'required|integer|min:1'
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()], []);
            }

            // Lógica para verificar el stock
            $product = Product::with(['productStock'])->where('id', $request->id_product)->first();
            if (!$product) {
                return ApiResponse::create('Producto no encontrado', 404, ['error' => 'Producto no encontrado en el presupuesto'], []);
            }

            $stock = 0;
            $quantity = $request->quantity;
            $days = $request->days;
            $dateFrom = \Carbon\Carbon::parse($request->date_from);
            $dateTo = $dateFrom->copy()->addDays($days);
            $availableStockPerDay = [];

            if (!$product->productStock) {
                $stock = $product->stock;
                for ($i = 0; $i < $days; $i++) {
                    $date = $dateFrom->copy()->addDays($i)->toDateString();
                    $used = ProductUseStock::where('id_product', $product->id)
                        ->where('date_from', '<=', $date)
                        ->where('date_to', '>=', $date)
                        ->sum('quantity');
                    $availableStockPerDay[$date] = $stock - $used;
                }
            } else {
                $stock = $product->productStock->stock;
                for ($i = 0; $i < $days; $i++) {
                    $date = $dateFrom->copy()->addDays($i)->toDateString();
                    $used = ProductUseStock::where('id_product_stock', $product->productStock->id)
                        ->where('date_from', '<=', $date)
                        ->where('date_to', '>=', $date)
                        ->sum('quantity');
                    $availableStockPerDay[$date] = $stock - $used;
                }
            }

            // Validar si en algún día no hay suficiente stock
            $hasInsufficientStock = collect($availableStockPerDay)->some(fn($available) => $available < $quantity);

            if ($hasInsufficientStock) {
                return ApiResponse::create('Stock insuficiente', 200, [
                    'error' => 'Stock insuficiente',
                    'stock' => false,
                    'product' => $product,
                    'available_stock' => $availableStockPerDay,
                    'requested_quantity' => $quantity
                ], []);
            }

            return ApiResponse::create('Stock verificado correctamente', 200, [
                'stock' => true,
                'product' => $product,
                'available_stock' => $availableStockPerDay,
                'requested_quantity' => $quantity,
            ], []);

        } catch (\Exception $e) {
            return ApiResponse::create('Error al verificar el stock', 500, ['error' => $e->getMessage()], []);
        }
    }

    // Se necesita hacer un endpoint que reciba id_product y fecha. Verificar en la tabla de historial de precios de productos, si ese producto tiene un precio cargado para ese dia.
    public function checkPrice(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_product' => 'required|integer|exists:products,id',
                'date' => 'required|date'
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()], []);
            }

            // Lógica para verificar el precio
            $product = Product::with(['prices'])
                ->where('id', $request->id_product)
                ->first();
            if (!$product) {
                return ApiResponse::create('Producto no encontrado', 404, ['error' => 'Producto no encontrado en el presupuesto'], []);
            }
            $price = $product->prices()
                ->where('valid_date_from', '<=', $request->date)
                ->where('valid_date_to', '>=', $request->date)
                ->first();

            if (!$price) {
                return ApiResponse::create('Precio no disponible', 200, [
                    'error' => 'Precio no disponible',
                    'has_price' => false,
                    'product' => $product,
                    'price' => $price
                ], []);
            }

            return ApiResponse::create('Precio verificado correctamente', 200, [
                'error' => 'Precio disponible',
                'has_price' => true,
                'product' => $product,
                'price' => $price
            ], []);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al verificar el precio', 500, ['error' => $e->getMessage()], []);
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
            // Buscar el presupuesto con sus relaciones necesarias
            $budget = Budget::with([
                'budgetStatus',
                'place',
                'transportation',
                'client',
                'budgetProducts.product'
            ])->findOrFail($id);

            // Cargar la vista del PDF
            $pdf = Pdf::loadView('pdf.budget', compact('budget'));

            // Asegurar la carpeta de destino
            if (!file_exists(public_path("storage/budgets/"))) {
                mkdir(public_path("storage/budgets/"), 0777, true);
            }

            // Guardar el PDF
            $pdf->save(public_path("storage/budgets/budget-{$budget->id}.pdf"));

            return ApiResponse::create('PDF regenerado correctamente', 200, [
                'pdf_path' => "storage/budgets/budget-{$budget->id}.pdf"
            ], [
                'module' => 'budget',
                'endpoint' => 'Regenerar PDF',
            ]);

        } catch (\Exception $e) {
            return ApiResponse::create('Error al regenerar el PDF', 500, ['error' => $e->getMessage()], [
                'module' => 'budget',
                'endpoint' => 'Regenerar PDF',
            ]);
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
