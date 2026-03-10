<?php

namespace App\Http\Controllers;

use App\Exports\ProductStockReportExport;
use App\Http\Responses\ApiResponse;
use App\Models\ProductAttributeValue;
use App\Models\ProductImage;
use App\Models\ProductPrice;
use App\Models\ProductProducts;
use App\Models\ProductUseStock;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelFormat;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Exception;
use Log;

class ProductController extends Controller
{
    // Obtener todos los productos con paginación
    public function index(Request $request)
    {
        try {
            // Detectar si el usuario autenticado es un cliente (tiene id_client poblado)
            $isClient = false;
            try {
                $user = JWTAuth::parseToken()->authenticate();
                if ($user && $user->id_client !== null) {
                    $isClient = true;
                }
            } catch (Exception $e) {
                // Usuario no autenticado o token inválido, no es cliente
            }

            $perPage = $request->query('per_page');
            $page = $request->query('page', 1);
            $status = $request->query('status');
            $type = $request->query('type');
            $line = $request->query('line');
            $furniture = $request->query('furniture');
            $sortOrder = $request->query('sort_order', 'asc');
            $dateStock = $request->query('date_stock'); // 🔹 fecha recibida

            $sortAlias = [
                'name' => 'products.name',
                'price' => 'products.price',
                'volume' => 'products.volume',
                'stock' => 'products.stock',
                'places_cant' => 'products.places_cant',
                'created_at' => 'products.created_at',
                'updated_at' => 'products.updated_at',
                'line' => 'product_lines.name',
                'type' => 'product_types.name',
                'furniture' => 'product_furnitures.name',
            ];

            $sortKey = $sortAlias[$request->query('sort_by')] ?? null;

            $query = Product::with([
                'productLine',
                'productType',
                'productFurniture',
                'productStatus',
                'prices',
                'mainImage',
                'attributeValues.attribute',
                'productUseStock', // 🔹 necesario para calcular stock usado
                'productStock',
                'comboItems.product.prices',
                'comboItems.product.productStock',
            ])
                ->join('product_lines', 'products.id_product_line', '=', 'product_lines.id')
                ->join('product_types', 'products.id_product_type', '=', 'product_types.id')
                ->join('product_furnitures', 'products.id_product_furniture', '=', 'product_furnitures.id')
                ->select('products.*');

            if (!is_null($status)) {
                $query->where('products.id_product_status', $status);
            }
            if (!is_null($type)) {
                $query->where('products.id_product_type', $type);
            }
            if (!is_null($line)) {
                $query->where('products.id_product_line', $line);
            }
            if (!is_null($furniture)) {
                $query->where('products.id_product_furniture', $furniture);
            }

            // Si es un usuario cliente, filtrar solo productos activos y de catálogo
            if ($isClient) {
                $query->where('products.show_catalog', true);
                $query->where('products.id_product_status', 1);
            }

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('products.name', 'like', '%' . $search . '%')
                        ->orWhere('products.description', 'like', '%' . $search . '%')
                        ->orWhere('products.code', 'like', '%' . $search . '%');
                });
            }

            if ($sortKey) {
                $query->orderBy($sortKey, $sortOrder === 'desc' ? 'desc' : 'asc');
            } else {
                $query->orderBy('products.name', 'asc');
            }

            if ($perPage !== null) {
                $products = $query->paginate($perPage, ['*'], 'page', $page);
                $data = $products->items();
                $meta_data = [
                    'page' => $products->currentPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'last_page' => $products->lastPage(),
                ];
            } else {
                $data = $query->get();
                $meta_data = null;
            }

            // 🔹 Ajustar el stock disponible según la fecha enviada
            if ($dateStock) {
                $date = \Carbon\Carbon::parse($dateStock)->toDateString();

                foreach ($data as $product) {
                    $baseStock = optional($product->productStock)->stock ?? $product->stock ?? 0;

                    $usedStock = $product->productUseStock
                        ->filter(fn($use) => $use->date_from <= $date && $use->date_to >= $date)
                        ->sum('quantity');

                    $product->available_stock = max(0, $baseStock - $usedStock);
                }
            } else {
                foreach ($data as $product) {
                    $product->available_stock = optional($product->productStock)->stock ?? $product->stock ?? 0;
                }
            }

            // 🔹 Filtrar precios según la fecha recibida
            if ($dateStock) {
                $targetDate = \Carbon\Carbon::parse($dateStock);

                foreach ($data as $product) {
                    $prices = $product->prices;

                    if ($prices->isEmpty()) {
                        // Sin precios: retornar array vacío
                        $product->setRelation('prices', collect([]));
                        continue;
                    }

                    // 1. Buscar precio donde la fecha encaje en el rango
                    $priceInRange = $prices->first(function ($price) use ($targetDate) {
                        $from = \Carbon\Carbon::parse($price->valid_date_from)->startOfDay();
                        $to = \Carbon\Carbon::parse($price->valid_date_to)->endOfDay();
                        return $targetDate->between($from, $to);
                    });

                    if ($priceInRange) {
                        $product->setRelation('prices', collect([$priceInRange]));
                        continue;
                    }

                    // 2. Buscar el precio más cercano ANTERIOR (valid_date_to < fecha)
                    $previousPrices = $prices->filter(function ($price) use ($targetDate) {
                        $to = \Carbon\Carbon::parse($price->valid_date_to)->endOfDay();
                        return $to->lt($targetDate);
                    })->sortByDesc(function ($price) {
                        return \Carbon\Carbon::parse($price->valid_date_to)->timestamp;
                    });

                    if ($previousPrices->isNotEmpty()) {
                        $product->setRelation('prices', collect([$previousPrices->first()]));
                        continue;
                    }

                    // 3. Buscar el precio más cercano POSTERIOR (valid_date_from > fecha)
                    $futurePrices = $prices->filter(function ($price) use ($targetDate) {
                        $from = \Carbon\Carbon::parse($price->valid_date_from)->startOfDay();
                        return $from->gt($targetDate);
                    })->sortBy(function ($price) {
                        return \Carbon\Carbon::parse($price->valid_date_from)->timestamp;
                    });

                    if ($futurePrices->isNotEmpty()) {
                        $product->setRelation('prices', collect([$futurePrices->first()]));
                        continue;
                    }

                    // 4. No hay precios (ya cubierto arriba, pero por seguridad)
                    $product->setRelation('prices', collect([]));
                }
            }

            return ApiResponse::paginate('Listado de productos obtenido correctamente', 200, $data, $meta_data, [
                'request' => $request,
                'module' => 'product',
                'endpoint' => 'Obtener todos los productos',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error inesperado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'product',
                'endpoint' => 'Obtener todos los productos',
            ]);
        }
    }

    // V1 - Obtener todos los productos con formato legacy
    public function indexV1(Request $request)
    {
        try {
            $perPage = $request->query('per_page');
            $page = $request->query('page', 1);

            $query = Product::with([
                'productLine',
                'productType',
                'productFurniture',
                'productUseStock',
                'prices',
                'attributeValues.attribute',
                'images',
            ])
                ->where('show_catalog', true)
                ->where('id_product_status', 1);

            $products = $query->get();

            $data = $products->map(function ($product) {
                // Extraer colores de attributeValues
                $color1 = '';
                $color2 = '';
                $colorAttributes = $product->attributeValues->filter(function ($av) {
                    return strtolower($av->attribute->name ?? '') === 'color';
                })->values();

                if ($colorAttributes->count() > 0) {
                    $color1 = $colorAttributes[0]->value ?? '';
                }
                if ($colorAttributes->count() > 1) {
                    $color2 = $colorAttributes[1]->value ?? '';
                }

                return [
                    'volumen' => $product->volume ? (float) $product->volume : null,
                    'stock' => $product->stock,
                    'productoStock' => $product->product_stock,
                    'tipoProducto' => $product->productType ? [
                        'tipo' => $product->productType->name,
                        'id' => $product->productType->id,
                        'state' => $product->productType->status,
                        'fecha_carga' => $product->productType->created_at ? $product->productType->created_at->format('Y-m-d') : null,
                        'hora_carga' => $product->productType->created_at ? $product->productType->created_at->format('H:i:s') : null,
                        'usuario_carga' => null,
                    ] : null,
                    'stocksProducto' => $product->productUseStock->map(function ($use) {
                        return [
                            'producto' => null,
                            'productoStock' => null,
                            'fechaDesde' => $use->date_from,
                            'fechaHasta' => $use->date_to,
                            'cantidad' => $use->quantity,
                            'id' => $use->id,
                            'state' => 1,
                            'fecha_carga' => $use->created_at ? $use->created_at->format('Y-m-d') : null,
                            'hora_carga' => $use->created_at ? $use->created_at->format('H:i:s') : null,
                            'usuario_carga' => null,
                        ];
                    })->values()->toArray(),
                    'cantidad' => null,
                    'tieneStock' => null,
                    'nombre' => $product->name,
                    'codigo' => $product->code,
                    'lineaProducto' => $product->productLine ? [
                        'linea' => $product->productLine->name,
                        'id' => $product->productLine->id,
                        'state' => $product->productLine->status,
                        'fecha_carga' => $product->productLine->created_at ? $product->productLine->created_at->format('Y-m-d') : null,
                        'hora_carga' => $product->productLine->created_at ? $product->productLine->created_at->format('H:i:s') : null,
                        'usuario_carga' => null,
                    ] : null,
                    'muebleProducto' => $product->productFurniture ? [
                        'mueble' => $product->productFurniture->name,
                        'id' => $product->productFurniture->id,
                        'state' => $product->productFurniture->status,
                        'fecha_carga' => $product->productFurniture->created_at ? $product->productFurniture->created_at->format('Y-m-d') : null,
                        'hora_carga' => $product->productFurniture->created_at ? $product->productFurniture->created_at->format('H:i:s') : null,
                        'usuario_carga' => null,
                    ] : null,
                    'descripcion' => $product->description,
                    'cantidadPlazas' => $product->places_cant,
                    'color1' => $color1,
                    'color2' => $color2,
                    'es_catalogo' => $product->show_catalog ? 1 : 0,
                    'fotos' => $product->images->map(function ($image) {
                        return [
                            'producto' => null,
                            'foto' => basename($image->image),
                            'id' => $image->id,
                            'state' => 1,
                            'fecha_carga' => $image->created_at ? $image->created_at->format('Y-m-d') : null,
                            'hora_carga' => $image->created_at ? $image->created_at->format('H:i:s') : null,
                            'usuario_carga' => null,
                        ];
                    })->values()->toArray(),
                    'precios' => $product->prices->map(function ($price) {
                        return [
                            'producto' => null,
                            'precio' => $price->price,
                            'vigenciaDesde' => \Carbon\Carbon::parse($price->valid_date_from)->format('Y-m-d'),
                            'vigenciaHasta' => \Carbon\Carbon::parse($price->valid_date_to)->format('Y-m-d'),
                            'cantidadMinima' => $price->minimun_quantity,
                            'clientesBonificados' => $price->client_bonification ? 1 : 0,
                            'id' => $price->id,
                            'state' => 1,
                            'fecha_carga' => $price->created_at ? $price->created_at->format('Y-m-d') : null,
                            'hora_carga' => $price->created_at ? $price->created_at->format('H:i:s') : null,
                            'usuario_carga' => null,
                        ];
                    })->values()->toArray(),
                    'id' => $product->id,
                    'state' => 1,
                    'fecha_carga' => $product->created_at ? $product->created_at->format('Y-m-d') : null,
                    'hora_carga' => $product->created_at ? $product->created_at->format('H:i:s') : null,
                    'usuario_carga' => null,
                ];
            });

            $total = $data->count();

            if ($perPage) {
                $perPage = (int) $perPage;
                $page = (int) $page;
                $paged = $data->forPage($page, $perPage)->values();
                $metaData = [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => (int) ceil($total / $perPage),
                ];
            } else {
                $paged = $data;
                $metaData = [
                    'page' => 1,
                    'per_page' => $total,
                    'total' => $total,
                    'last_page' => 1,
                ];
            }

            return response()->json([
                'code' => 1,
                'response' => 'Productos obtenidos correctamente',
                'data' => $paged,
                'meta_data' => $metaData,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'code' => 0,
                'response' => 'Error al obtener los productos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Obtener un producto por ID con toda su información
    public function show(Request $request, $id)
    {
        try {
            $product = Product::with([
                'productLine',
                'productType',
                'productFurniture',
                'productStatus',
                'productStock',
                'images',
                'attributeValues.attribute',
                'prices',
                'comboItems.product.productLine',
                'comboItems.product.productType',
                'comboItems.product.productFurniture',
                'comboItems.product.productStatus',
                'comboItems.product.productStock',
                'comboItems.product.images',
                'comboItems.product.attributeValues.attribute',
                'comboItems.product.prices',
            ])->findOrFail($id);

            //quitar id relacion

            return ApiResponse::create('Producto obtenido correctamente', 200, $product, [
                'request' => $request,
                'module' => 'product',
                'endpoint' => 'Obtener producto por ID',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error inesperado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'product',
                'endpoint' => 'Obtener producto por ID',
            ]);
        }
    }

    // Crear un nuevo producto
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:50|unique:products,code',
                'id_product_line' => 'required|integer|exists:product_lines,id',
                'id_product_type' => 'required|integer|exists:product_types,id',
                'id_product_furniture' => 'required|integer|exists:product_furnitures,id',
                'places_cant' => 'nullable|integer|min:0',
                'volume' => 'nullable|numeric|min:0',
                'description' => 'nullable|string',
                'stock' => 'nullable|integer|min:0',
                'product_stock' => 'nullable|integer|exists:products,id',
                'show_catalog' => 'required|boolean',
                'id_product_status' => 'nullable|integer|exists:product_status,id',
                'main_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // Imagen principal
                'images.*' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'values' => 'nullable|array',
                'values.*.id_product_attribute' => 'required|integer|exists:product_attributes,id',
                'values.*.value' => 'required|string|max:255',
                'prices' => 'nullable|array', // Validación para precios
                'prices.*.price' => 'required|numeric|min:0',
                'prices.*.valid_date_from' => 'required|date',
                'prices.*.valid_date_to' => 'required|date|after_or_equal:prices.*.valid_date_from',
                'prices.*.minimun_quantity' => 'required|integer|min:1',
                'prices.*.client_bonification' => 'required|boolean',
                'product_combo' => 'nullable|array',
                'product_combo.*.id_product' => 'required|integer|exists:products,id',
                'product_combo.*.quantity' => 'required|string|max:255',
            ]);

            $validator->after(function ($validator) use ($request) {
                if ((int) $request->input('id_product_type') === 2 && !$request->filled('product_combo')) {
                    $validator->errors()->add('product_combo', 'El campo product_combo es obligatorio cuando el tipo de producto es 2.');
                }
            });

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request' => $request,
                    'module' => 'product',
                    'endpoint' => 'Crear producto',
                ]);
            }

            // Crear el producto sin imágenes
            $product = Product::create($request->except(['main_image', 'images']));

            $imagePath = public_path('storage/product/img/'); // Ruta en public

            // Crear la carpeta si no existe
            if (!file_exists($imagePath)) {
                mkdir($imagePath, 0777, true);
            }

            // Guardar la imagen principal si se subió
            if ($request->hasFile('main_image')) {
                $mainImage = $request->file('main_image');
                $mainImageName = time() . '_main_' . $mainImage->getClientOriginalName();
                $mainImage->move($imagePath, $mainImageName);

                ProductImage::create([
                    'id_product' => $product->id,
                    'image' => '/storage/product/img/' . $mainImageName,
                    'is_main' => true
                ]);
            }

            // Guardar imágenes secundarias si existen
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $extraImage) {
                    $extraImageName = time() . '_extra_' . $extraImage->getClientOriginalName();
                    $extraImage->move($imagePath, $extraImageName);

                    ProductImage::create([
                        'id_product' => $product->id,
                        'image' => '/storage/product/img/' . $extraImageName,
                        'is_main' => false
                    ]);
                }
            }

            if ($request->has('values')) {
                foreach ($request->values as $attribute) {
                    ProductAttributeValue::create([
                        'id_product' => $product->id,
                        'id_product_attribute' => $attribute['id_product_attribute'],
                        'value' => $attribute['value']
                    ]);
                }
            }

            // Guardar precios si existen
            if ($request->has('prices')) {
                foreach ($request->prices as $price) {
                    ProductPrice::create([
                        'id_product' => $product->id,
                        'price' => $price['price'],
                        'valid_date_from' => $price['valid_date_from'],
                        'valid_date_to' => $price['valid_date_to'],
                        'minimun_quantity' => $price['minimun_quantity'],
                        'client_bonification' => $price['client_bonification'],
                    ]);
                }
            }

            if ($request->id_product_type === '2' && $request->has('product_combo')) {
                Log::info("Holaaaa");
                foreach ($request->product_combo as $item) {
                    ProductProducts::create([
                        'id_parent_product' => $product->id,
                        'id_product' => $item['id_product'],
                        'quantity' => $item['quantity'],
                    ]);
                }
            }

            // Cargar relaciones
            $product->load([
                'productLine',
                'productType',
                'productFurniture',
                'productStatus',
                'productStock',
                'images',
                'attributeValues.attribute',
                'prices',
                'comboItems.product',
            ]);

            return ApiResponse::create('Producto creado correctamente', 201, $product, [
                'request' => $request,
                'module' => 'product',
                'endpoint' => 'Crear producto',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error inesperado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'product',
                'endpoint' => 'Crear producto',
            ]);
        }
    }

    // Actualizar un producto existente
    public function update(Request $request, $id)
    {
        try {
            $product = Product::find($id);

            // validamos que el producto exista
            if (!$product) {
                return ApiResponse::create('Producto no encontrado', 404, ['error' => 'Product not found'], [
                    'request' => $request,
                    'module' => 'product',
                    'endpoint' => 'Actualizar producto',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:50|unique:products,code,' . $product->id,
                'id_product_line' => 'required|integer|exists:product_lines,id',
                'id_product_type' => 'required|integer|exists:product_types,id',
                'id_product_furniture' => 'required|integer|exists:product_furnitures,id',
                'places_cant' => 'nullable|integer|min:0',
                'volume' => 'nullable|numeric|min:0',
                'description' => 'nullable|string',
                'stock' => 'nullable|integer|min:0',
                'product_stock' => 'nullable|integer|exists:products,id',
                'show_catalog' => 'required|boolean',
                'id_product_status' => 'nullable|integer|exists:product_status,id',
                'main_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'existing_images.*.id' => 'nullable|integer|exists:products_images,id',
                'existing_images.*.image' => [
                    'nullable',
                    function ($attribute, $value, $fail) {
                        // Si es archivo, debe ser imagen válida
                        if ($value instanceof \Illuminate\Http\UploadedFile) {
                            if (!in_array($value->getClientOriginalExtension(), ['jpeg', 'jpg', 'png'])) {
                                return $fail('El archivo debe ser una imagen jpeg, jpg o png.');
                            }
                            if ($value->getSize() > 2048 * 1024) {
                                return $fail('El archivo no debe superar los 2MB.');
                            }
                        }
                        // Si es string, debe ser una ruta válida de storage
                        elseif (is_string($value)) {
                            // Aceptar tanto "/storage/..." como "storage/..."
                            if (!str_starts_with($value, '/storage/') && !str_starts_with($value, 'storage/')) {
                                return $fail('La cadena de imagen debe ser una ruta válida del almacenamiento.');
                            }
                        }
                    }
                ],
                'new_images.*' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'values' => 'nullable|array',
                'values.*.id' => 'nullable|integer|exists:product_attribute_values,id',
                'values.*.id_product_attribute' => 'required|integer|exists:product_attributes,id',
                'values.*.value' => 'required|string|max:255',
                'prices' => 'nullable|array',
                'prices.*.id' => 'nullable|integer|exists:product_prices,id',
                'prices.*.price' => 'required|numeric|min:0',
                'prices.*.valid_date_from' => 'required|date',
                'prices.*.valid_date_to' => 'required|date|after_or_equal:prices.*.valid_date_from',
                'prices.*.minimun_quantity' => 'required|integer|min:1',
                'prices.*.client_bonification' => 'required|boolean',
                'product_combo' => 'nullable|array',
                'product_combo.*.id' => 'nullable|integer|exists:product_products,id',
                'product_combo.*.id_parent_product' => 'required|integer|exists:products,id',
                'product_combo.*.id_product' => 'required|integer|exists:products,id',
                'product_combo.*.quantity' => 'required|string|max:255',
            ]);

            $validator->after(function ($validator) use ($request) {
                if ((int) $request->input('id_product_type') === 2 && !$request->filled('product_combo')) {
                    $validator->errors()->add('product_combo', 'El campo product_combo es obligatorio cuando el tipo de producto es 2.');
                }
            });

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request' => $request,
                    'module' => 'product',
                    'endpoint' => 'Actualizar producto',
                ]);
            }

            if (is_null($request->input('product_stock'))) {
                $product->product_stock = null;
                $product->save();
            }

            // Actualizar datos generales del producto
            $product->update($request->except(['main_image', 'images', 'values', 'prices']));

            $imagePath = public_path('storage/product/img/');

            // Actualizar imagen principal
            if ($request->hasFile('main_image')) {
                $currentMainImage = ProductImage::where('id_product', $product->id)->where('is_main', true)->first();
                if ($currentMainImage) {
                    $oldImagePath = public_path($currentMainImage->image);
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                    $currentMainImage->delete();
                }

                $mainImage = $request->file('main_image');
                $mainImageName = time() . '_main_' . $mainImage->getClientOriginalName();
                $mainImage->move($imagePath, $mainImageName);

                ProductImage::create([
                    'id_product' => $product->id,
                    'image' => '/storage/product/img/' . $mainImageName,
                    'is_main' => true
                ]);
            }

            // Agregar o actualizar imágenes existentes
            if ($request->has('existing_images')) {
                foreach ($request->existing_images as $index => $existingImage) {
                    // Guardar el índice en una variable
                    $imageIndex = $index;
                    $imageId = $existingImage['id'];  // id de la imagen que deseas editar
                    $newImageFile = $existingImage['image'];  // nueva imagen a reemplazar

                    Log::info($imageId);

                    // Verificar si el id de la imagen y la nueva imagen están presentes
                    if ($imageId && $newImageFile) {
                        if (is_string($newImageFile) && str_starts_with($newImageFile, '/storage/')) {
                            continue;
                        }
                        // Buscar el registro de la imagen por id
                        $imageRecord = ProductImage::find($imageId);

                        // Verificar si la imagen existe y no es la principal
                        if ($imageRecord && !$imageRecord->is_main) {
                            // Eliminar la imagen anterior
                            $oldImagePath = public_path($imageRecord->image);
                            if (file_exists($oldImagePath)) {
                                unlink($oldImagePath);  // Eliminar la imagen vieja
                            }

                            // Obtener la nueva imagen del archivo
                            $newImage = $request->file("existing_images.{$imageIndex}.image");

                            // Asegurarse de que se haya recibido la imagen y que sea válida
                            if ($newImage && $newImage->isValid()) {
                                // Generar un nuevo nombre para la imagen
                                $newImageName = time() . '_updated_' . $newImage->getClientOriginalName();

                                // Mover la imagen al directorio de almacenamiento
                                $newImage->move(public_path('storage/product/img/'), $newImageName);

                                // Actualizar el registro de la imagen con el nuevo archivo
                                $imageRecord->update([
                                    'image' => '/storage/product/img/' . $newImageName
                                ]);
                            } else {
                                Log::error('La imagen no es válida o no se ha recibido correctamente.');
                            }
                        } else {
                            Log::error('No se encontró el registro de la imagen o es la imagen principal.');
                        }
                    }
                }
            }

            // Eliminar imágenes que no están en la solicitud, pero no eliminar la imagen principal
            $existingImageIds = collect($request->existing_images)->pluck('id')->toArray();
            $imagesToDelete = ProductImage::where('id_product', $product->id)
                ->whereNotIn('id', $existingImageIds)
                ->where('is_main', false)  // Asegurarse de no eliminar la imagen principal
                ->get();

            foreach ($imagesToDelete as $image) {
                @unlink(public_path($image->image));  // Eliminar el archivo de imagen
                $image->delete();  // Eliminar el registro de la base de datos
            }

            if ($request->hasFile('new_images')) {
                foreach ($request->file('new_images') as $extraImage) {
                    $extraImageName = time() . '_extra_' . $extraImage->getClientOriginalName();
                    $extraImage->move($imagePath, $extraImageName);

                    ProductImage::create([
                        'id_product' => $product->id,
                        'image' => '/storage/product/img/' . $extraImageName,
                        'is_main' => false
                    ]);
                }
            }

            // Manejo de valores de atributos (editar, agregar, eliminar)
            if ($request->has('values')) {
                $existingAttributes = ProductAttributeValue::where('id_product', $product->id)->get()->keyBy('id');

                foreach ($request->values as $attribute) {
                    if (isset($attribute['id']) && $existingAttributes->has($attribute['id'])) {
                        // Si el atributo existe, actualizar
                        $existingAttributes[$attribute['id']]->update([
                            'id_product_attribute' => $attribute['id_product_attribute'],
                            'value' => $attribute['value']
                        ]);
                        $existingAttributes->forget($attribute['id']);
                    } else {
                        // Si no existe, agregarlo
                        ProductAttributeValue::create([
                            'id_product' => $product->id,
                            'id_product_attribute' => $attribute['id_product_attribute'],
                            'value' => $attribute['value']
                        ]);
                    }
                }

                // Eliminar atributos que no se enviaron
                foreach ($existingAttributes as $remainingAttribute) {
                    $remainingAttribute->delete();
                }
            }

            // Manejo de precios (editar, agregar, eliminar)
            // Manejo de precios (editar, agregar, eliminar)
            if ($request->has('prices')) {
                $existingPrices = ProductPrice::where('id_product', $product->id)->get()->keyBy('id');
                $sentPriceIds = [];

                foreach ($request->prices as $price) {
                    if (isset($price['id']) && $existingPrices->has($price['id'])) {
                        // Editar precio existente
                        $existingPrices[$price['id']]->update([
                            'price' => $price['price'],
                            'valid_date_from' => $price['valid_date_from'],
                            'valid_date_to' => $price['valid_date_to'],
                            'minimun_quantity' => $price['minimun_quantity'],
                            'client_bonification' => $price['client_bonification'],
                        ]);
                        $sentPriceIds[] = $price['id'];
                        $existingPrices->forget($price['id']);
                    } else {
                        // Crear nuevo precio
                        $newPrice = ProductPrice::create([
                            'id_product' => $product->id,
                            'price' => $price['price'],
                            'valid_date_from' => $price['valid_date_from'],
                            'valid_date_to' => $price['valid_date_to'],
                            'minimun_quantity' => $price['minimun_quantity'],
                            'client_bonification' => $price['client_bonification'],
                        ]);
                        $sentPriceIds[] = $newPrice->id;
                    }
                }

                // Eliminar precios que no se enviaron
                foreach ($existingPrices as $remainingPrice) {
                    $remainingPrice->delete();
                }
            }

            if ($request->id_product_type === '2' && $request->has('product_combo')) {
                $existingCombos = ProductProducts::where('id_parent_product', $product->id)->get()->keyBy('id');

                foreach ($request->product_combo as $comboItem) {
                    if (isset($comboItem['id']) && $existingCombos->has($comboItem['id'])) {
                        // Editar existente
                        $existingCombos[$comboItem['id']]->update([
                            'id_product' => $comboItem['id_product'],
                            'quantity' => $comboItem['quantity'],
                        ]);
                        // Eliminarlo de la colección para saber cuáles quedaron sin usar
                        $existingCombos->forget($comboItem['id']);
                    } else {
                        // Crear nuevo
                        ProductProducts::create([
                            'id_parent_product' => $product->id,
                            'id_product' => $comboItem['id_product'],
                            'quantity' => $comboItem['quantity'],
                        ]);
                    }
                }

                // Eliminar los combos que ya no están en la request
                foreach ($existingCombos as $remainingCombo) {
                    $remainingCombo->delete();
                }
            }

            // Cargar relaciones
            $product->load([
                'productLine',
                'productType',
                'productFurniture',
                'productStatus',
                'productStock',
                'mainImage',
                'images',
                'attributeValues.attribute',
                'prices',
                'comboItems.product',
            ]);

            return ApiResponse::create('Producto actualizado correctamente', 200, $product, [
                'request' => $request,
                'module' => 'product',
                'endpoint' => 'Actualizar producto',
            ]);

        } catch (Exception $e) {
            return ApiResponse::create('Error inesperado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'product',
                'endpoint' => 'Actualizar producto',
            ]);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $product = Product::find($id);

            if (!$product) {
                return ApiResponse::create('Producto no encontrado', 404, ['error' => 'Producto no encontrado'], [
                    'request' => $request,
                    'module' => 'product',
                    'endpoint' => 'Actualizar estado del producto',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'id_product_status' => 'required|exists:product_status,id'
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request' => $request,
                    'module' => 'product',
                    'endpoint' => 'Actualizar estado del producto',
                ]);
            }

            $product->id_product_status = $request->id_product_status;
            $product->save();

            $product->load([
                'productLine',
                'productType',
                'productFurniture',
                'productStatus',
                'productStock',
                'mainImage',
                'images',
                'attributeValues.attribute',
                'prices',
                'comboItems.product',
            ]);


            return ApiResponse::create('Estado actualizado correctamente', 201, $product, [
                'request' => $request,
                'module' => 'product',
                'endpoint' => 'Actualizar estado del producto',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar el estado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'product',
                'endpoint' => 'Actualizar estado del producto',
            ]);
        }
    }

    public function report7Days(Request $request)
    {
        try {
            $request->validate(['date' => 'required|date']);

            $startDate = \Carbon\Carbon::parse($request->date);
            $dates = collect(range(0, 6))->map(fn($i) => $startDate->copy()->addDays($i)->toDateString());

            $perPage = $request->query('per_page');
            $page = $request->query('page', 1);
            $type = $request->query('type');
            $line = $request->query('line');
            $furniture = $request->query('furniture');

            // Obtener productos usados
            $usedProductIds = ProductUseStock::distinct()->pluck('id_product')->toArray();

            $productsQuery = Product::with([
                'productStock',
                'productUseStock',
                'comboItems.product.productStock', // productos individuales del combo
            ])->whereIn('id', $usedProductIds);

            // Filtros adicionales
            if (!is_null($type))
                $productsQuery->where('products.id_product_type', $type);
            if (!is_null($line))
                $productsQuery->where('products.id_product_line', $line);
            if (!is_null($furniture))
                $productsQuery->where('products.id_product_furniture', $furniture);

            $products = $productsQuery->get();

            $groupedByStock = $products->groupBy(fn($product) => $product->product_stock ?? $product->id);

            $finalResult = [];

            foreach ($groupedByStock as $stockId => $group) {
                $representativeProduct = $group->firstWhere('id', $stockId) ?? $group->first();

                // Calculamos el uso diario del producto o combo
                $usedStockByDay = [];
                foreach ($dates as $date) {
                    $usedStockByDay[$date] = $group->flatMap(fn($p) => $p->productUseStock)
                        ->filter(fn($use) => $use->date_from <= $date && $use->date_to >= $date)
                        ->sum('quantity');
                }

                if ($representativeProduct->id_product_type == 2) {
                    // Producto combo → distribuir a productos individuales
                    foreach ($representativeProduct->comboItems as $comboItem) {
                        $inner = $comboItem->product;
                        if (!$inner)
                            continue;

                        foreach ($dates as $date) {
                            $finalResult[$inner->id]['used_stock_by_day'][$date] =
                                ($finalResult[$inner->id]['used_stock_by_day'][$date] ?? 0) +
                                ($usedStockByDay[$date] * $comboItem->quantity);
                        }

                        $finalResult[$inner->id]['id'] = $inner->id;
                        $finalResult[$inner->id]['name'] = $inner->name;
                        $finalResult[$inner->id]['code'] = $inner->code;
                        $finalResult[$inner->id]['stock'] = $inner->productStock->stock ?? $inner->stock;
                        $finalResult[$inner->id]['from_combo'][] = $representativeProduct->name;
                    }
                } else {
                    // Producto normal
                    $finalResult[$representativeProduct->id] = [
                        'id' => $representativeProduct->id,
                        'name' => $representativeProduct->name,
                        'code' => $representativeProduct->code,
                        'stock' => $representativeProduct->productStock->stock ?? $representativeProduct->stock,
                        'used_stock_by_day' => $usedStockByDay,
                    ];
                }
            }

            // Convertimos a colección y unimos from_combo si existe
            $result = collect($finalResult)->map(function ($item) {
                if (isset($item['from_combo']))
                    $item['from_combo'] = implode(', ', $item['from_combo']);
                return $item;
            })->values();

            // Filtrar productos sin alteración de stock
            $result = $result->filter(fn($item) => collect($item['used_stock_by_day'])->some(fn($v) => $v > 0))->values();

            // Ordenar alfabéticamente por nombre
            $result = $result->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

            // Filtrar por search si existe
            if ($request->has('search')) {
                $search = strtolower($request->query('search'));
                $result = $result->filter(fn($item) =>
                    strpos(strtolower($item['name']), $search) !== false ||
                    (isset($item['code']) && strpos(strtolower($item['code']), $search) !== false)
                )->values();
            }

            $total = $result->count();

            if ($perPage) {
                $perPage = (int) $perPage;
                $page = (int) $page;
                $paged = $result->forPage($page, $perPage)->values();
                $meta_data = [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage),
                ];
            } else {
                $paged = $result;
                $meta_data = [
                    'page' => 1,
                    'per_page' => $total,
                    'total' => $total,
                    'last_page' => 1,
                ];
            }

            return ApiResponse::paginate(
                'Reporte de uso de stock por producto obtenido correctamente',
                200,
                $paged,
                $meta_data,
                [
                    'request' => $request,
                    'module' => 'product',
                    'endpoint' => 'Reporte de uso de stock por producto',
                ]
            );
        } catch (\Exception $e) {
            return ApiResponse::create('Error inesperado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'product',
                'endpoint' => 'Reporte de uso de stock por producto',
            ]);
        }
    }


    public function reportMonth(Request $request)
    {
        try {
            $request->validate(['date' => 'required|date']);

            $start = \Carbon\Carbon::parse($request->date)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            $productUses = ProductUseStock::where(function ($query) use ($start, $end) {
                $query->whereBetween('date_from', [$start, $end])
                    ->orWhereBetween('date_to', [$start, $end]);
            })->with(['product', 'product.productStock', 'product.comboItems.product.productStock', 'budget.client', 'budget.place'])->get();

            $result = [];

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $currentDate = $date->toDateString();
                $usesForDate = $productUses->filter(fn($use) => $use->date_from <= $currentDate && $use->date_to >= $currentDate);

                $groupedByBudget = $usesForDate->groupBy('id_budget');
                $budgets = [];

                foreach ($groupedByBudget as $budgetId => $usesInBudget) {
                    $productsMap = [];

                    foreach ($usesInBudget as $use) {
                        $product = $use->product;
                        if (!$product)
                            continue;

                        if ($product->id_product_type == 2) {
                            // Combo → distribuir a productos individuales
                            foreach ($product->comboItems as $comboItem) {
                                $inner = $comboItem->product;
                                if (!$inner)
                                    continue;

                                $productsMap[$inner->id]['id'] = $inner->id;
                                $productsMap[$inner->id]['name'] = $inner->name;
                                $productsMap[$inner->id]['code'] = $inner->code;
                                $productsMap[$inner->id]['stock'] = $inner->productStock->stock ?? $inner->stock;
                                $productsMap[$inner->id]['used_stock'] = ($productsMap[$inner->id]['used_stock'] ?? 0) + ($use->quantity * $comboItem->quantity);
                            }
                        } else {
                            // Producto normal
                            $productsMap[$product->id]['id'] = $product->id;
                            $productsMap[$product->id]['name'] = $product->name;
                            $productsMap[$product->id]['code'] = $product->code;
                            $productsMap[$product->id]['stock'] = $product->productStock->stock ?? $product->stock;
                            $productsMap[$product->id]['used_stock'] = ($productsMap[$product->id]['used_stock'] ?? 0) + $use->quantity;
                        }
                    }

                    $firstUse = $usesInBudget->first();
                    $budget = $firstUse?->budget;

                    $budgets[] = [
                        'id_budget' => $budgetId,
                        'date_event' => $budget?->date_event,
                        'days' => $budget?->days,
                        'volume' => $budget?->volume,
                        'place' => $budget?->place ? [
                            'id' => $budget->place->id,
                            'name' => $budget->place->name,
                            'distance' => $budget->place->distance,
                        ] : null,
                        'client' => $budget?->client ? [
                            'id' => $budget->client->id,
                            'name' => $budget->client->name,
                        ] : ($budget ? ['id' => null, 'name' => $budget->client_name] : null),
                        'products' => array_values($productsMap),
                    ];
                }

                $result[] = [
                    'date' => $currentDate,
                    'budgets' => $budgets,
                ];
            }

            return ApiResponse::create('Reporte generado correctamente', 200, $result, [
                'request' => $request,
                'module' => 'product',
                'endpoint' => 'Reporte mensual agrupado por fecha y presupuesto',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error inesperado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'product',
                'endpoint' => 'Reporte mensual agrupado por fecha y presupuesto',
            ]);
        }
    }


    public function catalog(Request $request)
    {
        try {
            // Validar parámetro date
            $validator = Validator::make($request->all(), [
                'date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, $validator->errors()->toArray(), [
                    'request' => $request,
                    'module' => 'product',
                    'endpoint' => 'Catálogo de productos para clientes',
                ]);
            }

            $targetDate = \Carbon\Carbon::parse($request->query('date'));
            $dateString = $targetDate->toDateString();

            $perPage = $request->query('per_page');
            $page = $request->query('page', 1);

            $query = Product::with([
                'productFurniture',
                'attributeValues.attribute',
                'productUseStock',
                'productStock',
                'prices',
            ])
                ->where('show_catalog', true)
                ->where('id_product_status', 1);

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('code', 'like', '%' . $search . '%');
                });
            }

            $query->orderBy('name', 'asc');

            if ($perPage !== null) {
                $paginated = $query->paginate($perPage, ['*'], 'page', $page);
                $products = $paginated->items();
                $meta_data = [
                    'page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                ];
            } else {
                $products = $query->get()->all();
                $meta_data = null;
            }

            $measureAttributeNames = ['dimensiones', 'altura'];

            $data = array_map(function ($product) use ($dateString, $targetDate, $measureAttributeNames) {
                // Disponibilidad
                $baseStock = optional($product->productStock)->stock ?? $product->stock ?? 0;
                $usedStock = $product->productUseStock
                    ->filter(fn($use) => $use->date_from <= $dateString && $use->date_to >= $dateString)
                    ->sum('quantity');
                $availability = max(0, $baseStock - $usedStock);

                // Medidas (Dimensiones y Altura)
                $dimensions = $product->attributeValues
                    ->filter(fn($av) => in_array(strtolower($av->attribute->name ?? ''), $measureAttributeNames))
                    ->map(fn($av) => [
                        'attribute' => $av->attribute->name,
                        'value' => $av->value,
                    ])
                    ->values()
                    ->toArray();

                // Precio y price_status
                $price = null;
                $priceStatus = null;
                $prices = $product->prices;

                if ($prices->isNotEmpty()) {
                    // 1. Precio vigente en la fecha
                    $priceInRange = $prices->first(function ($p) use ($targetDate) {
                        $from = \Carbon\Carbon::parse($p->valid_date_from)->startOfDay();
                        $to = \Carbon\Carbon::parse($p->valid_date_to)->endOfDay();
                        return $targetDate->between($from, $to);
                    });

                    if ($priceInRange) {
                        $price = (float) $priceInRange->price;
                        $priceStatus = 'current';
                    } else {
                        // 2. Precio anterior más cercano
                        $previousPrice = $prices->filter(function ($p) use ($targetDate) {
                            return \Carbon\Carbon::parse($p->valid_date_to)->endOfDay()->lt($targetDate);
                        })->sortByDesc(function ($p) {
                            return \Carbon\Carbon::parse($p->valid_date_to)->timestamp;
                        })->first();

                        if ($previousPrice) {
                            $price = (float) $previousPrice->price;
                            $priceStatus = 'previous';
                        } else {
                            // 3. Precio futuro más cercano
                            $futurePrice = $prices->filter(function ($p) use ($targetDate) {
                                return \Carbon\Carbon::parse($p->valid_date_from)->startOfDay()->gt($targetDate);
                            })->sortBy(function ($p) {
                                return \Carbon\Carbon::parse($p->valid_date_from)->timestamp;
                            })->first();

                            if ($futurePrice) {
                                $price = (float) $futurePrice->price;
                                $priceStatus = 'future';
                            }
                        }
                    }
                }

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'code' => $product->code,
                    'furniture_type' => $product->productFurniture ? [
                        'id' => $product->productFurniture->id,
                        'name' => $product->productFurniture->name,
                    ] : null,
                    'availability' => $availability,
                    'dimensions' => $dimensions,
                    'price' => $price,
                    'price_status' => $priceStatus,
                ];
            }, $products);

            return ApiResponse::paginate('Catálogo de productos obtenido correctamente', 200, $data, $meta_data, [
                'request' => $request,
                'module' => 'product',
                'endpoint' => 'Catálogo de productos para clientes',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error inesperado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'product',
                'endpoint' => 'Catálogo de productos para clientes',
            ]);
        }
    }

    public function exportReport7Days(Request $request)
    {
        try {
            $date = $request->query('date');
            if (!$date) {
                return response()->json(['error' => 'Debe proporcionar una fecha'], 400);
            }

            $startDate = \Carbon\Carbon::parse($date);
            $dates = collect(range(0, 6))->map(fn($i) => $startDate->copy()->addDays($i)->toDateString());

            $usedProductIds = ProductUseStock::distinct()->pluck('id_product')->toArray();

            $products = Product::with(['productStock', 'productUseStock'])
                ->whereIn('id', $usedProductIds)
                ->get();

            $groupedByStock = $products->groupBy(fn($p) => $p->product_stock ?? $p->id);

            $result = collect();

            foreach ($groupedByStock as $stockId => $group) {
                $representativeProduct = $group->firstWhere('id', $stockId) ?? $group->first();
                $stock = $representativeProduct->productStock->stock ?? $representativeProduct->stock;

                $usedStock = [];
                foreach ($dates as $dateItem) {
                    $totalUsed = $group->flatMap(fn($p) => $p->productUseStock)
                        ->filter(fn($use) => $use->date_from <= $dateItem && $use->date_to >= $dateItem)
                        ->sum('quantity');
                    $usedStock[$dateItem] = $totalUsed;
                }

                $result->push([
                    'id' => $representativeProduct->id,
                    'name' => $representativeProduct->name,
                    'code' => $representativeProduct->code,
                    'stock' => $stock,
                    'show_catalog' => $representativeProduct->show_catalog ? 'Sí' : 'No',
                    'used_stock_by_day' => $usedStock,
                ]);
            }

            $fileName = 'stock_usage_' . now()->format('Ymd_His') . '.xlsx';
            $directory = public_path('storage/reports');

            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            $filePath = $directory . '/' . $fileName;

            $writer = Excel::raw(new ProductStockReportExport($result, $dates), ExcelFormat::XLSX);
            file_put_contents($filePath, $writer);

            return ApiResponse::create('Archivo exportado correctamente', 200, [
                'file_url' => 'reports/' . $fileName,
            ], [
                'request' => $request,
                'module' => 'product stock',
                'endpoint' => 'Exportar reporte de uso de stock',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al exportar el reporte de uso de stock', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'product stock',
                'endpoint' => 'Exportar reporte de uso de stock',
            ]);
        }
    }
}
