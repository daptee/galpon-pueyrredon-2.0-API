<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Budget;
use App\Models\BudgetAudith;
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
        $perPage = $request->query('per_page');
        $page = $request->query('page', 1);
        $startDate = $request->input('start_date', now()->toDateString());

        // 1. Obtener todos los presupuestos con relaciones
        $allBudgets = Budget::with(['client', 'place', 'budgetStatus'])->get();

        // 2. Convertir a array y ordenar por cercanía a la fecha
        $allBudgets = $allBudgets->sortBy(function ($budget) use ($startDate) {
            return abs(strtotime($budget->date_event) - strtotime($startDate));
        })->values();

        $allBudgetsArray = json_decode(json_encode($allBudgets), true);

        // 3. Indexar por ID para acceso rápido
        $budgetById = [];
        foreach ($allBudgetsArray as $budget) {
            $budget['budgets'] = []; // Inicializar hijos o padres
            $budgetById[$budget['id']] = $budget;
        }

        // 4. Filtrar según parámetros
        $filtered = array_filter($budgetById, function ($budget) use ($request) {
            if ($request->has('place') && $budget['id_place'] != $request->input('place')) return false;
            if ($request->has('status') && $budget['id_budget_status'] != $request->input('status')) return false;
            if ($request->has('client') && $budget['id_client'] != $request->input('client')) return false;
            if ($request->has('event_date') && $budget['date_event'] != $request->input('event_date')) return false;
            if ($request->has('start_date') && $budget['date_event'] < $request->input('start_date')) return false;
            if ($request->has('search')) {
                $search = $request->input('search');
                if (!str_contains((string)$budget['id'], $search)) return false;
            }
            return true;
        });

        // 5. Construir árbol de padres para cada presupuesto (hijos)
        $finalBudgets = [];

        foreach ($filtered as $budget) {
            $current = $budget;
            $visited = [];

            // Recursivamente agregar padres
            while ($current['id_budget']) {
                $parentId = $current['id_budget'];

                // Prevención de loops infinitos
                if (in_array($parentId, $visited)) break;
                $visited[] = $parentId;

                if (isset($budgetById[$parentId])) {
                    $parent = $budgetById[$parentId];
                    $parent['budgets'] = [$current];
                    $current = $parent;
                } else {
                    break;
                }
            }

            $finalBudgets[] = $current;
        }

        // Eliminar duplicados
        $finalBudgets = collect($finalBudgets)->unique('id')->values()->all();
        $total = count($finalBudgets);

        // 6. Aplicar paginación
        if ($perPage) {
            $paged = array_slice($finalBudgets, ($page - 1) * $perPage, $perPage);
            $meta_data = [
                'page' => $page,
                'per_page' => (int)$perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
            ];
        } else {
            $paged = $finalBudgets;
            $meta_data = null;
        }

        return ApiResponse::paginate('Presupuestos obtenidos correctamente', 200, $paged, $meta_data, [
            'request' => $request,
            'module' => 'budget',
            'endpoint' => 'Obtener todos los presupuestos agrupados descendente',
        ]);
    } catch (\Exception $e) {
        return ApiResponse::create('Error al obtener los presupuestos', 500, ['error' => $e->getMessage()], [
            'request' => $request,
            'module' => 'budget',
            'endpoint' => 'Obtener todos los presupuestos agrupados descendente',
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
                'budgetProducts.product',
                'budgetDeliveryData'
            ])->find($id);

            if (!$budget) {
                return ApiResponse::create('Presupuesto no encontrado', 404, ['error' => 'Presupuesto no encontrado'], []);
            }

            return ApiResponse::create('Presupuesto obtenido correctamente', 200, $budget, [
                'request' => request(),
                'module' => 'budget',
                'endpoint' => 'Obtener presupuesto por ID',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener el presupuesto', 500, ['error' => $e->getMessage()], [
                'request' => request(),
                'module' => 'budget',
                'endpoint' => 'Obtener presupuesto por ID',
            ]);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_client' => 'nullable|integer|exists:clients,id',
                'client_name' => 'nullable|string|max:255',
                'client_mail' => 'required|email',
                'client_phone' => 'required|string',
                'id_place' => 'required|integer|exists:places,id',
                'id_transportation' => 'required|integer|exists:transportations,id',
                'date_event' => 'required|date',
                'time_event' => 'nullable|string',
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
                'products_has_stock' => 'required|boolean',
                'products_has_prices' => 'required|boolean',
                'id_budget' => 'nullable|integer|exists:budgets,id',
                'observations' => 'nullable|string',
                'volume' => 'nullable|numeric',
                'product' => 'required|array|min:1',
                'product.*.id_product' => 'required|integer|exists:products,id',
                'product.*.quantity' => 'required|integer|min:1',
                'product.*.price' => 'required|numeric',
                'product.*.has_stock' => 'required|boolean',
                'product.*.has_price' => 'required|boolean',
                'product.*.client_bonification' => 'nullable|boolean',
            ]);

            $validator->after(function ($validator) use ($request) {
                if (empty($request->id_client) && empty($request->client_name)) {
                    $validator->errors()->add('client_name', 'El nombre del cliente es obligatorio si no se selecciona un cliente existente.');
                }
            });

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request' => $request,
                    'module' => 'budget',
                    'endpoint' => 'Crear presupuesto',
                ]);
            }

            $data = $request->all();

            if (!empty($data['id_budget'])) {
                $parentBudget = Budget::find($data['id_budget']);
                if (!$parentBudget) {
                    return ApiResponse::create('Presupuesto padre no encontrado', 404, ['error' => 'Presupuesto padre no encontrado'], [
                        'request' => $request,
                        'module' => 'budget',
                        'endpoint' => 'Crear presupuesto',
                    ]);
                }
                if ($data['id_budget_status'] !== 1) {
                    $parentBudget->id_budget_status = 5;
                    $parentBudget->save();

                    BudgetAudith::create([
                        'id_budget' => $parentBudget->id,
                        'action' => 'update_status',
                        'new_budget_status' => $parentBudget->id_budget_status,
                        'observations' => json_encode([
                            'id_budget_status' => $parentBudget->id_budget_status,
                            'status_name' => $parentBudget->budgetStatus->name
                        ]),
                        'user' => auth()->user()->id,
                        'date' => now()->toDateString(),
                        'time' => now()->toTimeString()
                    ]);
                }

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
                    'client_bonification' => $item['client_bonification'] ?? false,
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

            if ($data['id_budget_status'] == 2) {
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

                $pdfPath = public_path("storage/budgets/budget-{$budget->id}.pdf");

                \Mail::to($budget->client_mail)->send(new \App\Mail\BudgetCreated($budget, $pdfPath, auth()->user()));
            }

            // agregar auditoría
            BudgetAudith::create([
                'id_budget' => $budget->id,
                'action' => 'create',
                'new_budget_status' => $budget->id_budget_status,
                'observations' => $data['observations'] ?? null,
                'user' => auth()->user()->id,
                'date' => now()->toDateString(),
                'time' => now()->toTimeString()
            ]);

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
                'observations' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()], []);
            }

            // si no se envían observaciones, se pone en null
            $budget->observations = $request->observations ?? null;
            $budget->save();

            // agregar auditoría
            BudgetAudith::create([
                'id_budget' => $budget->id,
                'action' => 'update_observations',
                'new_budget_status' => $budget->id_budget_status,
                'observations' => $budget->observations,
                'user' => auth()->user()->id,
                'date' => now()->toDateString(),
                'time' => now()->toTimeString()
            ]);

            return ApiResponse::create('Observaciones actualizadas correctamente', 201, $budget, [
                'request' => $request,
                'module' => 'budget',
                'endpoint' => 'Actualizar observaciones',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar observaciones', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'budget',
                'endpoint' => 'Actualizar observaciones',
            ]);
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
                            'date_to' => \Carbon\Carbon::parse($budget->date_event)->addDays($budget->days - 1),
                            'quantity' => $item->quantity
                        ]);
                    }
                }
                ;
            }

            // agregar auditoría
            BudgetAudith::create([
                'id_budget' => $budget->id,
                'action' => 'update_status',
                'new_budget_status' => $budget->id_budget_status,
                'observations' => json_encode([
                    'id_budget_status' => $budget->id_budget_status,
                    'status_name' => $budget->budgetStatus->name
                ]),
                'user' => auth()->user()->id,
                'date' => now()->toDateString(),
                'time' => now()->toTimeString()
            ]);

            return ApiResponse::create('Estado actualizado correctamente', 201, $budget, [
                'request' => $request,
                'module' => 'budget',
                'endpoint' => 'Actualizar estado del presupuesto',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar el estado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'budget',
                'endpoint' => 'Actualizar estado del presupuesto',
            ]);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_client' => 'nullable|integer|exists:clients,id',
                'client_name' => 'nullable|string|max:255',
                'client_mail' => 'required|email',
                'client_phone' => 'required|string',
                'id_place' => 'required|integer|exists:places,id',
                'id_transportation' => 'required|integer|exists:transportations,id',
                'date_event' => 'required|date',
                'time_event' => 'nullable|string',
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
                'products_has_stock' => 'required|boolean',
                'products_has_prices' => 'required|boolean',
                'id_budget' => 'nullable|integer|exists:budgets,id',
                'observations' => 'nullable|string',
                'volume' => 'nullable|numeric',
                'product' => 'required|array|min:1',
                'product.*.id_product' => 'required|integer|exists:products,id',
                'product.*.quantity' => 'required|integer|min:1',
                'product.*.price' => 'required|numeric',
                'product.*.has_stock' => 'required|boolean',
                'product.*.has_price' => 'required|boolean',
                'product.*.client_bonification' => 'nullable|boolean',
            ]);

            $validator->after(function ($validator) use ($request) {
                if (empty($request->id_client) && empty($request->client_name)) {
                    $validator->errors()->add('client_name', 'El nombre del cliente es obligatorio si no se selecciona un cliente existente.');
                }
            });

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request' => $request,
                    'module' => 'budget',
                    'endpoint' => 'Crear presupuesto',
                ]);
            }

            $budget = Budget::find($id);
            if (!$budget) {
                return ApiResponse::create('Presupuesto no encontrado', 404, null, [
                    'request' => $request,
                    'module' => 'budget',
                    'endpoint' => 'Editar presupuesto',
                ]);
            }
            // solo se puede editar si es borrador id_status 1

            if ($budget->id_budget_status !== 1) {
                return ApiResponse::create('Presupuesto no editable', 403, ['error' => 'Solo se pueden editar presupuestos en estado borrador'], [
                    'request' => $request,
                    'module' => 'budget',
                    'endpoint' => 'Editar presupuesto',
                ]);
            }
            ;

            $data = $request->all();

            // Si tiene presupuesto padre, actualizar su estado
            if (!empty($data['id_budget'])) {
                $parentBudget = Budget::find($data['id_budget']);
                if ($parentBudget && $data['id_budget'] == 3) {
                    $parentBudget->id_budget_status = 5;
                    $parentBudget->save();

                    BudgetAudith::create([
                        'id_budget' => $parentBudget->id,
                        'action' => 'update_status',
                        'new_budget_status' => $parentBudget->id_budget_status,
                        'observations' => json_encode([
                            'id_budget_status' => $parentBudget->id_budget_status,
                            'status_name' => $parentBudget->budgetStatus->name
                        ]),
                        'user' => auth()->user()->id,
                        'date' => now()->toDateString(),
                        'time' => now()->toTimeString()
                    ]);
                }
            }

            // Actualizar presupuesto
            $budget->update($data);

            // Eliminar productos anteriores
            BudgetProducts::where('id_budget', $budget->id)->delete();

            // Crear los nuevos productos
            foreach ($data['product'] as $item) {
                BudgetProducts::create([
                    'id_budget' => $budget->id,
                    'id_product' => $item['id_product'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'has_stock' => $item['has_stock'],
                    'has_price' => $item['has_price'],
                    'client_bonification' => $item['client_bonification'] ?? false,
                ]);
            }

            // Recargar relaciones
            $budget->load([
                'budgetStatus',
                'place',
                'transportation',
                'client',
                'budgetProducts.product'
            ]);

            if ($data['id_budget_status'] == 2) {
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

                $pdfPath = public_path("storage/budgets/budget-{$budget->id}.pdf");

                \Mail::to($budget->client_mail)->send(new \App\Mail\BudgetCreated($budget, $pdfPath, auth()->user()));
            }

            // Auditoría de actualización
            BudgetAudith::create([
                'id_budget' => $budget->id,
                'action' => 'update',
                'new_budget_status' => $budget->id_budget_status,
                'observations' => $data['observations'] ?? null,
                'user' => auth()->user()->id,
                'date' => now()->toDateString(),
                'time' => now()->toTimeString()
            ]);

            return ApiResponse::create('Presupuesto actualizado correctamente', 200, $budget, [
                'request' => $request,
                'module' => 'budget',
                'endpoint' => 'Editar presupuesto',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar el presupuesto', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'budget',
                'endpoint' => 'Editar presupuesto',
            ]);
        }
    }

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
            ], [
                'module' => 'budget',
                'endpoint' => 'Verificar stock del producto',
            ]);

        } catch (\Exception $e) {
            return ApiResponse::create('Error al verificar el stock', 500, ['error' => $e->getMessage()], [
                'module' => 'budget',
                'endpoint' => 'Verificar stock del producto',
            ]);
        }
    }
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
            ], [
                'module' => 'budget',
                'endpoint' => 'Verificar precio del producto',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al verificar el precio', 500, ['error' => $e->getMessage()], [
                'module' => 'budget',
                'endpoint' => 'Verificar precio del producto',
            ]);
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

            // agregar auditoría
            BudgetAudith::create([
                'id_budget' => $budget->id,
                'action' => 'update_contact',
                'new_budget_status' => $budget->id_budget_status,
                'observations' => json_encode([
                    'client_mail' => $budget->client_mail,
                    'client_phone' => $budget->client_phone
                ]),
                'user' => auth()->user()->id,
                'date' => now()->toDateString(),
                'time' => now()->toTimeString()
            ]);

            return ApiResponse::create('Contacto actualizado correctamente', 201, $budget, [
                'request' => $request,
                'module' => 'budget',
                'endpoint' => 'Actualizar contacto del presupuesto',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar el contacto', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'budget',
                'endpoint' => 'Actualizar contacto del presupuesto',
            ]);
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

            // agregar auditoría

            BudgetAudith::create([
                'id_budget' => $budget->id,
                'action' => 'generate_pdf',
                'new_budget_status' => $budget->id_budget_status,
                'observations' => 'PDF generado correctamente',
                'user' => auth()->user()->id,
                'date' => now()->toDateString(),
                'time' => now()->toTimeString()
            ]);

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

    public function generatePdfDeliveryInformation($id)
    {
        try {
            // Buscar el presupuesto con sus relaciones necesarias
            $budget = Budget::with([
                'budgetStatus',
                'place',
                'transportation',
                'client',
                'budgetProducts.product',
                'budgetDeliveryData'
            ])->findOrFail($id);

            // Si el evento NO tiene cargado los datos de entrega, retornar un error en la peticion de que no hay datos de entrega cargados.

            if (!$budget->budgetDeliveryData) {
                return ApiResponse::create('Datos de entrega no encontrados', 404, ['error' => 'No hay datos de entrega cargados para este presupuesto'], [
                    'module' => 'budget',
                    'endpoint' => 'Generar PDF de información de entrega',
                ]);
            }

            // Cargar la vista del PDF
            $pdf = Pdf::loadView('pdf.deliveryInformation', compact('budget'));

            // Asegurar la carpeta de destino
            if (!file_exists(public_path("storage/delivery_information/"))) {
                mkdir(public_path("storage/delivery_information/"), 0777, true);
            }

            // Guardar el PDF
            $pdf->save(public_path("storage/delivery_information/budget-{$budget->id}.pdf"));

            // agregar auditoría

            BudgetAudith::create([
                'id_budget' => $budget->id,
                'action' => 'generate_pdf',
                'new_budget_status' => $budget->id_budget_status,
                'observations' => 'PDF generado correctamente',
                'user' => auth()->user()->id,
                'date' => now()->toDateString(),
                'time' => now()->toTimeString()
            ]);

            return ApiResponse::create('PDF regenerado correctamente', 200, [
                'pdf_path' => "storage/delivery_information/budget-{$budget->id}.pdf"
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

    public function sendMails(Request $request, $id)
    {
        try {

            $validator = Validator::make($request->all(), [
                'mails' => 'required|array|min:1',
                'mails.*' => 'required|email',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()], []);
            }

            $budget = Budget::with(['client'])->findOrFail($id);

            if (!$budget) {
                return ApiResponse::create('Presupuesto no encontrado', 404, ['error' => 'Presupuesto no encontrado'], []);
            }

            $pdfPath = public_path("storage/budgets/budget-{$budget->id}.pdf");

            if (!file_exists($pdfPath)) {
                return ApiResponse::create('PDF no encontrado', 404, ['error' => 'PDF no encontrado'], []);
            }

            // Enviar el email a cada dirección proporcionada
            foreach ($request->mails as $email) {
                \Mail::to($email)->send(new \App\Mail\BudgetCreated($budget, $pdfPath, auth()->user()));
            }

            // agregar auditoría
            BudgetAudith::create([
                'id_budget' => $budget->id,
                'action' => 'send_mails',
                'new_budget_status' => $budget->id_budget_status,
                'observations' => json_encode($request->mails),
                'user' => auth()->user()->id,
                'date' => now()->toDateString(),
                'time' => now()->toTimeString()
            ]);

            return ApiResponse::create('Mails enviados correctamente', 200, [
                'message' => 'Mails enviados correctamente',
                'budget_id' => $budget->id,
                'mails' => $request->mails
            ], [
                'module' => 'budget',
                'endpoint' => 'Enviar mails',
            ]);

        } catch (\Exception $e) {
            return ApiResponse::create('Error al enviar los mails', 500, ['error' => $e->getMessage()], [
                'module' => 'budget',
                'endpoint' => 'Enviar mails',
            ]);
        }
    }

}
