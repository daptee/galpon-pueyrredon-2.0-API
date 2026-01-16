<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Budget;
use App\Models\BudgetAudith;
use App\Models\BudgetDeliveryData;
use App\Models\BudgetProducts;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductProducts;
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
            $perPage = $request->query('per_page'); // ahora sin valor por defecto
            $page = $request->query('page', 1);

            // 1. Obtener todos los presupuestos con relaciones
            $startDate = $request->input('start_date', now()->toDateString());

            $allBudgets = Budget::with(['client', 'place', 'budgetStatus'])->get();

            // Ordenar por cercanía a start_date
            $allBudgets = $allBudgets->sortBy(function ($budget) use ($startDate) {
                return abs(strtotime($budget->date_event) - strtotime($startDate));
            })->values();

            // 2. Aplicar filtros manualmente
            $filtered = $allBudgets->filter(function ($budget) use ($request) {
                if ($request->has('place') && $budget->id_place != $request->input('place'))
                    return false;
                if ($request->has('status') && $budget->id_budget_status != $request->input('status'))
                    return false;
                if ($request->has('client') && $budget->id_client != $request->input('client'))
                    return false;
                if ($request->has('event_date') && $budget->date_event != $request->input('event_date'))
                    return false;
                if ($request->has('start_date') && $budget->date_event < $request->input('start_date'))
                    return false;
                if ($request->has('search')) {
                    $search = strtolower($request->input('search'));

                    // Buscar por ID de presupuesto
                    $matchesId = str_contains(strtolower((string) $budget->id), $search);

                    // Buscar por nombre del cliente (campo client_name en budgets)
                    $clientName = strtolower($budget->client_name ?? '');
                    $matchesClientName = str_contains($clientName, $search);

                    // Si no coincide con ninguno, filtrar
                    if (!$matchesId && !$matchesClientName)
                        return false;
                }
                return true;
            });

            // 3. Transformar a array
            $filteredArray = json_decode(json_encode($filtered), true);

            // 4. Indexar por ID
            $byId = [];
            foreach ($filteredArray as $budget) {
                $budget['budgets'] = []; // inicializar hijos
                $byId[$budget['id']] = $budget;
            }

            // 5. Construir árbol anidando al revés (padres dentro de hijos)
            foreach ($byId as $id => &$budget) {
                if ($budget['id_budget'] && isset($byId[$budget['id_budget']])) {
                    // Anidar el padre dentro del hijo
                    $budget['budgets'][] = $byId[$budget['id_budget']];
                    // Una vez anidado el padre, lo eliminamos del array principal para que no quede duplicado
                    unset($byId[$budget['id_budget']]);
                }
            }
            unset($budget); // limpiar referencia

            // 6. Lo que queda son los nodos más profundos (los que van en la raíz)
            $tree = array_values($byId);

            $total = count($tree);

            // 6. Aplicar paginación si viene per_page
            if ($perPage) {
                $paged = array_slice($tree, ($page - 1) * $perPage, $perPage);
                $meta_data = [
                    'page' => $page,
                    'per_page' => (int) $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage),
                ];
            } else {
                $paged = $tree; // sin paginar
                $meta_data = null;
            }

            return ApiResponse::paginate('Presupuestos obtenidos correctamente', 200, $paged, $meta_data, [
                'request' => $request,
                'module' => 'budget',
                'endpoint' => 'Obtener todos los presupuestos agrupados',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener los presupuestos', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'budget',
                'endpoint' => 'Obtener todos los presupuestos agrupados',
            ]);
        }
    }


    public function show($id)
    {
        try {
            $budget = Budget::with([
                'budgetStatus',
                'place.locality',
                'place.province',
                'transportation',
                'client',
                'budgetProducts.product',
                'budgetDeliveryData'
            ])->find($id);

            if (!$budget) {
                return ApiResponse::create(
                    'Presupuesto no encontrado',
                    404,
                    ['error' => 'Presupuesto no encontrado'],
                    []
                );
            }

            // Días de duración del evento
            $eventDays = $budget->days ?? 1; // ajusta según tu modelo

            // Procesamos productos para calcular disponibilidad
            foreach ($budget->budgetProducts as $budgetProduct) {
                $product = $budgetProduct->product;

                if (!$product) {
                    $budgetProduct->availability = false;
                    continue;
                }

                // Si es combo (id_product_type == 2)
                if ($product->id_product_type == 2) {
                    $comboRelations = ProductProducts::where('id_parent_product', $product->id)->with('product')->get();
                    $comboAvailable = true;
                    $childrenDetails = [];

                    foreach ($comboRelations as $relation) {
                        $child = $relation->product;
                        if (!$child)
                            continue;

                        // Cantidad requerida = cantidad en el presupuesto * cantidad del hijo * días de evento
                        $requiredQty = $budgetProduct->quantity * $relation->quantity * $eventDays;
                        $hasStock = $child->stock >= $requiredQty;

                        $childrenDetails[] = [
                            'id' => $child->id,
                            'name' => $child->name,
                            'volume' => $child->volume,
                            'quantity_in_combo' => $relation->quantity,
                            'required' => $requiredQty,
                            'stock' => $child->stock,
                            'available' => $hasStock,
                        ];

                        if (!$hasStock) {
                            $comboAvailable = false;
                        }
                    }

                    $budgetProduct->availability = $comboAvailable;
                    $budgetProduct->combo_items = $childrenDetails;

                } else {
                    // Producto simple
                    $requiredQty = $budgetProduct->quantity * $eventDays;
                    $budgetProduct->availability = $product->stock >= $requiredQty;
                }
            }

            return ApiResponse::create(
                'Presupuesto obtenido correctamente',
                200,
                $budget,
                [
                    'request' => request(),
                    'module' => 'budget',
                    'endpoint' => 'Obtener presupuesto por ID',
                ]
            );
        } catch (\Exception $e) {
            return ApiResponse::create(
                'Error al obtener el presupuesto',
                500,
                ['error' => $e->getMessage()],
                [
                    'request' => request(),
                    'module' => 'budget',
                    'endpoint' => 'Obtener presupuesto por ID',
                ]
            );
        }
    }

    public function checkBudget(Request $request)
    {
        try {
            $rules = [
                'client_id' => 'required|integer|exists:clients,id',
                'date_event' => 'required|date',
                'place_id' => 'required|integer|exists:places,id',
            ];

            $validated = $request->validate($rules);

            // Buscar presupuestos con esos 3 parámetros
            $budgets = Budget::where('id_client', $validated['client_id'])
                ->where('date_event', $validated['date_event'])
                ->where('id_place', $validated['place_id'])
                ->pluck('id'); // solo traigo los ids de presupuestos encontrados

            if ($budgets->isEmpty()) {
                return ApiResponse::create(
                    'No existe presupuesto con esos datos',
                    200,
                    ['exists' => false, 'budgets' => []],
                    [
                        'request' => $request,
                        'module' => 'budget',
                        'endpoint' => 'Validar presupuesto duplicado',
                    ]
                );
            }

            return ApiResponse::create(
                'Ya existe presupuesto con esos datos',
                200,
                ['exists' => true, 'budgets' => $budgets],
                [
                    'request' => $request,
                    'module' => 'budget',
                    'endpoint' => 'Validar presupuesto duplicado',
                ]
            );
        } catch (\Exception $e) {
            return ApiResponse::create(
                'Error al validar presupuesto',
                500,
                ['error' => $e->getMessage()],
                [
                    'request' => $request,
                    'module' => 'budget',
                    'endpoint' => 'Validar presupuesto duplicado',
                ]
            );
        }
    }

    public function treeStatus($id, Request $request)
    {
        try {
            $budget = Budget::with(['client', 'place', 'budgetStatus'])->find($id);

            if (!$budget) {
                return ApiResponse::create(
                    'Presupuesto no encontrado',
                    404,
                    ['error' => 'Presupuesto no encontrado'],
                    []
                );
            }

            // Buscar padres recursivamente
            $parents = collect();
            $current = $budget;
            while ($current && $current->id_budget) {
                $parent = Budget::with(['client', 'place', 'budgetStatus'])->find($current->id_budget);
                if ($parent) {
                    $parents->push($parent);
                    $current = $parent;
                } else {
                    break;
                }
            }

            // Buscar hijos recursivamente
            $children = collect();
            $stack = collect([$budget]);
            while ($stack->isNotEmpty()) {
                $node = $stack->pop();
                $kids = Budget::with(['client', 'place', 'budgetStatus'])
                    ->where('id_budget', $node->id)
                    ->get();
                foreach ($kids as $child) {
                    $children->push($child);
                    $stack->push($child);
                }
            }

            // Unir todos (padres + actual + hijos)
            $all = $parents->reverse()->merge([$budget])->merge($children);

            // Agrupar por id_budget_status
            $grouped = $all->groupBy(function ($item) {
                return $item->budgetStatus->name ?? 'Sin Estado';
            });

            return ApiResponse::create(
                'Árbol de presupuesto obtenido correctamente',
                200,
                $grouped,
                [
                    'request' => $request,
                    'module' => 'budget',
                    'endpoint' => 'Obtener árbol de presupuesto',
                ]
            );
        } catch (\Exception $e) {
            return ApiResponse::create(
                'Error al obtener árbol de presupuesto',
                500,
                ['error' => $e->getMessage()],
                [
                    'request' => $request,
                    'module' => 'budget',
                    'endpoint' => 'Obtener árbol de presupuesto',
                ]
            );
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
                'quoted_days' => 'required|numeric|min:1',
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

            if ($request->id_budget_status == 3) {

                // Función para obtener todo el árbol (padres e hijos)

                $allBudgets = $this->getBudgetTree($budget->id);

                // Excluir el presupuesto que estamos aprobando
                $toClose = $allBudgets->filter(fn($b) => $b->id !== $budget->id);

                foreach ($budget->budgetProducts as $item) {
                    ProductUseStock::create([
                        'id_budget' => $budget->id,
                        'id_product' => $item->id_product,
                        'id_product_stock' => $item->product->product_stock == null ? $item->id_product : $item->product->product_stock,
                        'date_from' => $budget->date_event,
                        'date_to' => \Carbon\Carbon::parse($budget->date_event)->addDays($budget->days - 1),
                        'quantity' => $item->quantity
                    ]);
                }

                foreach ($toClose as $b) {
                    $b->id_budget_status = 5; // Cerrado/aceptado automáticamente
                    $b->save();

                    // Auditoría de estado
                    BudgetAudith::create([
                        'id_budget' => $b->id,
                        'action' => 'CAMBIO DE ESTADO',
                        'new_budget_status' => $b->id_budget_status,
                        'observations' => 'Se reemplazo por su version mas nueva',
                        'user' => auth()->user()->id,
                        'date' => now()->toDateString(),
                        'time' => now()->toTimeString()
                    ]);

                    // Delivery data
                    $deliveryData = BudgetDeliveryData::where('id_budget', $b->id_budget)->first();
                    if ($deliveryData) {
                        $deliveryData->id_budget = $b->id;
                        $deliveryData->save();

                        BudgetAudith::create([
                            'id_budget' => $b->id,
                            'action' => 'ACTUALIZACION DE DATOS DE DELIVERY',
                            'new_budget_status' => $b->id_budget_status,
                            'observations' => 'Se asigna delivery data al nuevo presupuesto',
                            'user' => auth()->user()->id,
                            'date' => now()->toDateString(),
                            'time' => now()->toTimeString()
                        ]);
                    }

                    // Pasar pagos del presupuesto cerrado a la nueva versión aprobada
                    $payments = Payment::where('id_budget', $b->id)->get();
                    foreach ($payments as $payment) {
                        $payment->id_budget = $budget->id;
                        $payment->save();
                    }

                    BudgetAudith::create([
                        'id_budget' => $b->id,
                        'action' => 'ACTUALIZACION DATOS DE PAGOS',
                        'new_budget_status' => $b->id_budget_status,
                        'observations' => 'Se asignan pagos del presupuesto cerrado',
                        'user' => auth()->user()->id,
                        'date' => now()->toDateString(),
                        'time' => now()->toTimeString()
                    ]);

                    // Eliminar stock usado
                    ProductUseStock::where('id_budget', $b->id)->delete();

                    BudgetAudith::create([
                        'id_budget' => $b->id,
                        'action' => 'ELIMINACION DE STOCK USADO',
                        'new_budget_status' => $b->id_budget_status,
                        'observations' => 'Se elimina stock usado al cerrar presupuesto',
                        'user' => auth()->user()->id,
                        'date' => now()->toDateString(),
                        'time' => now()->toTimeString()
                    ]);
                }
            }

            if ($data['id_budget_status'] == 2 || $data['id_budget_status'] == 3) {
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

                $to = config('app.env') === 'testing' || config('app.env') === 'local'
                    ? env('MAIL_REDIRECT_TO', 'galponpueyrredon@hotmail.com')
                    : $budget->client_mail;

                Log::info('Enviando presupuesto por correo', [
                    'to' => $to,
                    'budget_id' => $budget->id,
                    'pdf_path' => $pdfPath
                ]);

                \Mail::to($to)->send(new \App\Mail\BudgetCreated($budget, $pdfPath, auth()->user()));
            }

            // agregar auditoría
            BudgetAudith::create([
                'id_budget' => $budget->id,
                'action' => 'ALTA',
                'new_budget_status' => $budget->id_budget_status,
                'observations' => 'Presupuesto creado',
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
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], []);
            }

            // si no se envían observaciones, se pone en null
            $budget->observations = $request->observations ?? null;
            $budget->save();

            // agregar auditoría
            BudgetAudith::create([
                'id_budget' => $budget->id,
                'action' => 'ACTUALIZAR OBSERVACIONES',
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
                'id_budget_status' => 'required|exists:budget_status,id',
                'observations_status' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], []);
            }

            $budget->id_budget_status = $request->id_budget_status;
            $budget->save();

            $budget->load([
                'budgetStatus',
                'budgetProducts.product'
            ]);

            // Si el presupuesto se aprueba
            if ($request->id_budget_status == 3) {

                // Función para obtener todo el árbol (padres e hijos)

                $allBudgets = $this->getBudgetTree($budget->id);

                // Excluir el presupuesto que estamos aprobando
                $toClose = $allBudgets->filter(fn($b) => $b->id !== $budget->id);

                foreach ($budget->budgetProducts as $item) {
                    ProductUseStock::create([
                        'id_budget' => $budget->id,
                        'id_product' => $item->id_product,
                        'id_product_stock' => $item->product->product_stock == null ? $item->id_product : $item->product->product_stock,
                        'date_from' => $budget->date_event,
                        'date_to' => \Carbon\Carbon::parse($budget->date_event)->addDays($budget->days - 1),
                        'quantity' => $item->quantity
                    ]);
                }

                foreach ($toClose as $b) {
                    $b->id_budget_status = 5; // Cerrado/aceptado automáticamente
                    $b->save();

                    // Auditoría de estado
                    BudgetAudith::create([
                        'id_budget' => $b->id,
                        'action' => 'CAMBIO DE ESTADO',
                        'new_budget_status' => $b->id_budget_status,
                        'observations' => 'Se reemplazo por su version mas nueva',
                        'user' => auth()->user()->id,
                        'date' => now()->toDateString(),
                        'time' => now()->toTimeString()
                    ]);

                    // Delivery data
                    $deliveryData = BudgetDeliveryData::where('id_budget', $b->id_budget)->first();
                    if ($deliveryData) {
                        $deliveryData->id_budget = $b->id;
                        $deliveryData->save();

                        BudgetAudith::create([
                            'id_budget' => $b->id,
                            'action' => 'ACTUALIZACION DE DATOS DE DELIVERY',
                            'new_budget_status' => $b->id_budget_status,
                            'observations' => 'Se asigna delivery data al nuevo presupuesto',
                            'user' => auth()->user()->id,
                            'date' => now()->toDateString(),
                            'time' => now()->toTimeString()
                        ]);
                    }

                    // Pasar pagos del presupuesto cerrado a la nueva versión aprobada
                    $payments = Payment::where('id_budget', $b->id)->get();
                    foreach ($payments as $payment) {
                        $payment->id_budget = $budget->id;
                        $payment->save();
                    }

                    BudgetAudith::create([
                        'id_budget' => $b->id,
                        'action' => 'ACTUALIZACION DATOS DE PAGOS',
                        'new_budget_status' => $b->id_budget_status,
                        'observations' => 'Se asignan pagos del presupuesto cerrado',
                        'user' => auth()->user()->id,
                        'date' => now()->toDateString(),
                        'time' => now()->toTimeString()
                    ]);

                    // Eliminar stock usado
                    ProductUseStock::where('id_budget', $b->id)->delete();

                    BudgetAudith::create([
                        'id_budget' => $b->id,
                        'action' => 'ELIMINACION DE STOCK USADO',
                        'new_budget_status' => $b->id_budget_status,
                        'observations' => 'Se elimina stock usado al cerrar presupuesto',
                        'user' => auth()->user()->id,
                        'date' => now()->toDateString(),
                        'time' => now()->toTimeString()
                    ]);
                }
            } else {
                // si no está en estado 3 eliminamos uso de stock
                $usedStocks = ProductUseStock::where('id_budget', $budget->id)->get();
                if ($usedStocks && $usedStocks->count() > 0) {
                    foreach ($usedStocks as $usedStock) {
                        $usedStock->delete();
                    }
                }
            }

            if ($request->id_budget_status == 2 || $request->id_budget_status == 3) {
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

                $to = config('app.env') === 'testing' || config('app.env') === 'local'
                    ? env('MAIL_REDIRECT_TO', 'galponpueyrredon@hotmail.com')
                    : $budget->client_mail;

                Log::info('Enviando presupuesto por correo', [
                    'to' => $to,
                    'budget_id' => $budget->id,
                    'pdf_path' => $pdfPath
                ]);

                \Mail::to($to)->send(new \App\Mail\BudgetCreated($budget, $pdfPath, auth()->user()));
            }

            // agregar auditoría
            BudgetAudith::create([
                'id_budget' => $budget->id,
                'action' => 'CAMBIO DE ESTADO',
                'new_budget_status' => $budget->id_budget_status,
                'observations' => $request->observations_status ?? 'Estado actualizado',
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
                'quoted_days' => 'required|numeric|min:1',
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
                    'endpoint' => 'Editar presupuesto',
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

            $data = $request->all();

            if ($request->id_budget_status == 3) {

                // Función para obtener todo el árbol (padres e hijos)

                $allBudgets = $this->getBudgetTree($budget->id);

                // Excluir el presupuesto que estamos aprobando
                $toClose = $allBudgets->filter(fn($b) => $b->id !== $budget->id);

                foreach ($budget->budgetProducts as $item) {
                    ProductUseStock::create([
                        'id_budget' => $budget->id,
                        'id_product' => $item->id_product,
                        'id_product_stock' => $item->product->product_stock == null ? $item->id_product : $item->product->product_stock,
                        'date_from' => $budget->date_event,
                        'date_to' => \Carbon\Carbon::parse($budget->date_event)->addDays($budget->days - 1),
                        'quantity' => $item->quantity
                    ]);
                }

                foreach ($toClose as $b) {
                    $b->id_budget_status = 5; // Cerrado/aceptado automáticamente
                    $b->save();

                    // Auditoría de estado
                    BudgetAudith::create([
                        'id_budget' => $b->id,
                        'action' => 'ACTUALIZACION DE ESTADO',
                        'new_budget_status' => $b->id_budget_status,
                        'observations' => 'Se reemplazo por su version mas nueva',
                        'user' => auth()->user()->id,
                        'date' => now()->toDateString(),
                        'time' => now()->toTimeString()
                    ]);

                    // Delivery data
                    $deliveryData = BudgetDeliveryData::where('id_budget', $b->id_budget)->first();
                    if ($deliveryData) {
                        $deliveryData->id_budget = $b->id;
                        $deliveryData->save();

                        BudgetAudith::create([
                            'id_budget' => $b->id,
                            'action' => 'ACTUALIZACION DE DATOS DE DELIVERY',
                            'new_budget_status' => $b->id_budget_status,
                            'observations' => 'Se asigna delivery data al nuevo presupuesto',
                            'user' => auth()->user()->id,
                            'date' => now()->toDateString(),
                            'time' => now()->toTimeString()
                        ]);
                    }

                    // Pasar pagos del presupuesto cerrado a la nueva versión aprobada
                    $payments = Payment::where('id_budget', $b->id)->get();
                    foreach ($payments as $payment) {
                        $payment->id_budget = $budget->id;
                        $payment->save();
                    }

                    BudgetAudith::create([
                        'id_budget' => $b->id,
                        'action' => 'ACTUALIZACION DATOS DE PAGOS',
                        'new_budget_status' => $b->id_budget_status,
                        'observations' => 'Se asignan pagos del presupuesto cerrado',
                        'user' => auth()->user()->id,
                        'date' => now()->toDateString(),
                        'time' => now()->toTimeString()
                    ]);

                    // Eliminar stock usado
                    ProductUseStock::where('id_budget', $b->id)->delete();

                    BudgetAudith::create([
                        'id_budget' => $b->id,
                        'action' => 'ELIMINACION DE STOCK USADO',
                        'new_budget_status' => $b->id_budget_status,
                        'observations' => 'Se elimina stock usado al cerrar presupuesto',
                        'user' => auth()->user()->id,
                        'date' => now()->toDateString(),
                        'time' => now()->toTimeString()
                    ]);
                }
            } else {
                // si no está en estado 3 eliminamos uso de stock
                $usedStocks = ProductUseStock::where('id_budget', $budget->id)->get();
                if ($usedStocks && $usedStocks->count() > 0) {
                    foreach ($usedStocks as $usedStock) {
                        $usedStock->delete();
                    }
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

            // Generar PDF y enviar email en estados 2 o 3
            if ($data['id_budget_status'] == 2 || $data['id_budget_status'] == 3) {
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

                $to = config('app.env') === 'testing' || config('app.env') === 'local'
                    ? env('MAIL_REDIRECT_TO', 'galponpueyrredon@hotmail.com')
                    : $budget->client_mail;

                Log::info('Enviando presupuesto por correo', [
                    'to' => $to,
                    'budget_id' => $budget->id,
                    'pdf_path' => $pdfPath
                ]);

                \Mail::to($to)->send(new \App\Mail\BudgetCreated($budget, $pdfPath, auth()->user()));
            }

            // Auditoría de actualización
            BudgetAudith::create([
                'id_budget' => $budget->id,
                'action' => 'EDICIÓN',
                'new_budget_status' => $budget->id_budget_status,
                'observations' => 'Presupuesto editado',
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
                'id_budget' => 'nullable|integer|exists:budgets,id',
                'quantity' => 'required|integer|min:1',
                'date_from' => 'required|date',
                'days' => 'required|integer|min:1'
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], []);
            }

            // 🔹 Por defecto ignoramos el presupuesto actual (si existe)
            $idBudgetToIgnore = $request->id_budget;

            // 🔹 Verificar si hay que usar otro presupuesto aprobado del árbol
            if ($request->id_budget) {
                $budget = Budget::find($request->id_budget);

                if ($budget) {
                    // Si NO está aprobado
                    if ($budget->id_budget_status != 3) {
                        // Buscar presupuestos relacionados (padres e hijos)
                        $relatedBudgets = collect();

                        // Padres
                        $current = $budget;
                        while ($current && $current->id_budget) {
                            $parent = Budget::find($current->id_budget);
                            if ($parent) {
                                $relatedBudgets->push($parent);
                                $current = $parent;
                            } else {
                                break;
                            }
                        }

                        // Hijos (recursivo)
                        $stack = collect([$budget]);
                        while ($stack->isNotEmpty()) {
                            $node = $stack->pop();
                            $children = Budget::where('id_budget', $node->id)->get();
                            foreach ($children as $child) {
                                $relatedBudgets->push($child);
                                $stack->push($child);
                            }
                        }

                        // Buscar un presupuesto aprobado en el árbol
                        $approved = $relatedBudgets->firstWhere('id_budget_status', 3);

                        // Si encontramos uno aprobado, lo usamos para ignorar stock
                        if ($approved) {
                            $idBudgetToIgnore = $approved->id;
                        }
                    }
                }
            }

            // 🔹 A partir de acá sigue igual, solo usamos $idBudgetToIgnore

            $product = Product::with([
                'productStock',
                'comboItems.product.productStock'
            ])->find($request->id_product);

            if (!$product) {
                return ApiResponse::create('Producto no encontrado', 404, ['error' => 'Producto no encontrado en el presupuesto'], []);
            }

            $quantity = $request->quantity;
            $days = $request->days;
            $dateFrom = \Carbon\Carbon::parse($request->date_from);

            $availableStockPerDay = [];
            $childrenDetails = [];
            $hasInsufficientStock = false;

            // 🔹 Caso COMBO
            if ($product->id_product_type == 2) {
                foreach ($product->comboItems as $comboItem) {
                    $childProduct = $comboItem->product;
                    $requiredQuantity = $quantity * $comboItem->quantity;
                    $childAvailablePerDay = [];
                    $childHasStock = true;

                    $stock = $childProduct->productStock
                        ? $childProduct->productStock->stock
                        : $childProduct->stock;

                    for ($i = 0; $i < $days; $i++) {
                        $date = $dateFrom->copy()->addDays($i)->toDateString();

                        $used = ProductUseStock::when(
                            $childProduct->productStock,
                            function ($q) use ($childProduct, $date) {
                                return $q->where('id_product_stock', $childProduct->productStock->id);
                            },
                            function ($q) use ($childProduct, $date) {
                                return $q->where('id_product', $childProduct->id);
                            }
                        )
                            ->where('date_from', '<=', $date)
                            ->where('date_to', '>=', $date)
                            ->when($idBudgetToIgnore, function ($q) use ($idBudgetToIgnore) {
                                // ✅ Ignorar stock reservado para el presupuesto aprobado o el actual
                                return $q->where('id_budget', '<>', $idBudgetToIgnore);
                            })
                            ->sum('quantity');

                        $available = $stock - $used;
                        $childAvailablePerDay[$date] = $available;

                        if ($available < $requiredQuantity) {
                            $childHasStock = false;
                            $hasInsufficientStock = true;
                        }
                    }

                    $childrenDetails[] = [
                        'product' => $childProduct,
                        'required_quantity' => $requiredQuantity,
                        'available_stock' => $childAvailablePerDay,
                        'stock_ok' => $childHasStock
                    ];
                }
            } else {
                // 🔹 Caso PRODUCTO INDIVIDUAL
                $stock = $product->productStock ? $product->productStock->stock : $product->stock;

                for ($i = 0; $i < $days; $i++) {
                    $date = $dateFrom->copy()->addDays($i)->toDateString();

                    $used = ProductUseStock::when(
                        $product->productStock,
                        function ($q) use ($product, $date) {
                            return $q->where('id_product_stock', $product->productStock->id);
                        },
                        function ($q) use ($product, $date) {
                            return $q->where('id_product', $product->id);
                        }
                    )
                        ->where('date_from', '<=', $date)
                        ->where('date_to', '>=', $date)
                        ->when($idBudgetToIgnore, function ($q) use ($idBudgetToIgnore) {
                            // ✅ Ignorar stock reservado para el presupuesto aprobado o el actual
                            return $q->where('id_budget', '<>', $idBudgetToIgnore);
                        })
                        ->sum('quantity');

                    $available = $stock - $used;
                    $availableStockPerDay[$date] = $available;

                    if ($available < $quantity) {
                        $hasInsufficientStock = true;
                    }
                }
            }

            // 🔹 Respuesta final
            if ($hasInsufficientStock) {
                return ApiResponse::create('Stock insuficiente', 200, [
                    'error' => 'Stock insuficiente',
                    'stock' => false,
                    'product' => $product,
                    'available_stock' => $product->id_product_type == 2 ? null : $availableStockPerDay,
                    'requested_quantity' => $quantity,
                    'check_stock_combo' => $product->id_product_type == 2 ? $childrenDetails : null
                ], []);
            }

            return ApiResponse::create('Stock verificado correctamente', 200, [
                'stock' => true,
                'product' => $product,
                'available_stock' => $product->id_product_type == 2 ? null : $availableStockPerDay,
                'requested_quantity' => $quantity,
                'check_stock_combo' => $product->id_product_type == 2 ? $childrenDetails : null
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
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], []);
            }

            $product = Product::with(['prices', 'comboItems.product.prices'])
                ->where('id', $request->id_product)
                ->first();

            if (!$product) {
                return ApiResponse::create('Producto no encontrado', 404, ['error' => 'Producto no encontrado en el presupuesto'], []);
            }

            // 🔹 Buscamos precio directo del producto
            $price = $product->prices()
                ->where('valid_date_from', '<=', $request->date)
                ->where('valid_date_to', '>=', $request->date)
                ->first();

            // 🔹 Si no tiene precio directo pero es combo
            if (!$price && $product->id_product_type == 2) {
                $totalPrice = 0;
                $missingExactPrice = false;
                $hasClosestPrice = false;

                foreach ($product->comboItems as $comboItem) {
                    $childProduct = $comboItem->product;

                    // Buscamos precio exacto del producto hijo
                    $childPrice = $childProduct->prices()
                        ->where('valid_date_from', '<=', $request->date)
                        ->where('valid_date_to', '>=', $request->date)
                        ->first();

                    // Si no hay precio exacto, lo marcamos pero buscamos el más cercano para estimar
                    if (!$childPrice) {
                        $missingExactPrice = true;

                        $childPrice = $childProduct->prices()
                            ->orderByRaw('ABS(DATEDIFF(valid_date_from, ?))', [$request->date])
                            ->first();

                        if ($childPrice) {
                            $hasClosestPrice = true;
                        }
                    }

                    // Si no hay ningún precio (ni exacto ni cercano)
                    if (!$childPrice) {
                        return ApiResponse::create('Precio no disponible para un producto del combo', 200, [
                            'error' => 'Precio no disponible para un producto del combo',
                            'has_price' => false,
                            'product' => $product,
                            'price' => null
                        ], []);
                    }

                    $totalPrice += $childPrice->price * $comboItem->quantity;
                }

                $price = (object) [
                    'id_product' => $product->id,
                    'price' => number_format($totalPrice, 2, '.', ''),
                ];

                // 🔸 Si todos los hijos tenían precio válido → tiene precio
                if (!$missingExactPrice) {
                    $product->makeHidden('comboItems');

                    return ApiResponse::create('Precio verificado correctamente', 200, [
                        'error' => 'Precio disponible',
                        'has_price' => true,
                        'product' => $product,
                        'price' => $price
                    ], [
                        'module' => 'budget',
                        'endpoint' => 'Verificar precio del producto',
                    ]);
                }

                // 🔸 Si faltaban precios exactos → usamos estimado, pero devolvemos "no disponible"
                return ApiResponse::create('Precio no disponible', 200, [
                    'error' => 'Precio no disponible',
                    'has_price' => false,
                    'product' => $product,
                    'price' => $price // estimado
                ], []);
            }

            // 🔹 Si no es combo y no tiene precio directo
            if (!$price) {
                return ApiResponse::create('Precio no disponible', 200, [
                    'error' => 'Precio no disponible',
                    'has_price' => false,
                    'product' => $product,
                    'price' => null
                ], []);
            }

            $product->makeHidden('comboItems');

            // 🔹 Caso normal: tiene precio exacto
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
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], []);
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
                'action' => 'ACTUALIZACION DE CONTACTO',
                'new_budget_status' => $budget->id_budget_status,
                'observations' => 'Contacto actualizado correctamente',
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
                'action' => 'GENERAR PDF',
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
                'budgetProducts.product.attributeValues',
                'budgetDeliveryData'
            ])->find($id);

            // Si el evento NO tiene cargado los datos de entrega, retornar un error en la peticion de que no hay datos de entrega cargados.

            if (!$budget) {
                return ApiResponse::create('Presupuesto no encontrados', 404, ['error' => 'Este presupuesto no existe'], [
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
                'action' => 'GENERAR PDF',
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
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], []);
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
                'action' => 'ENVIO DE MAILS',
                'new_budget_status' => $budget->id_budget_status,
                'observations' => 'Mails enviados a: ' . implode(', ', $request->mails),
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

    private function getBudgetTree($budgetId)
    {
        $budget = Budget::find($budgetId);
        if (!$budget)
            return collect();

        // Padres
        $parents = collect();
        $current = $budget;
        while ($current && $current->id_budget) {
            $parent = Budget::find($current->id_budget);
            if ($parent) {
                $parents->push($parent);
                $current = $parent;
            } else {
                break;
            }
        }

        // Hijos
        $children = collect();
        $stack = collect([$budget]);
        while ($stack->isNotEmpty()) {
            $node = $stack->pop();
            $kids = Budget::where('id_budget', $node->id)->get();
            foreach ($kids as $child) {
                $children->push($child);
                $stack->push($child);
            }
        }

        // Todos
        return $parents->reverse()->merge([$budget])->merge($children);
    }


}
