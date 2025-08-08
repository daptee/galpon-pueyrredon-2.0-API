<?php

namespace App\Http\Controllers;

use App\Exports\ProductStockReportExport;
use App\Exports\StockUsageExport;
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
use Exception;
use Log;

class ProductController extends Controller
{
    // Obtener todos los productos con paginación
    public function index(Request $request)
    {
        try {
            $perPage = $request->query('per_page');
            $page = $request->query('page', 1);
            $status = $request->query('status');
            $type = $request->query('type');
            $line = $request->query('line');
            $furniture = $request->query('furniture');
            $sortOrder = $request->query('sort_order', 'asc'); // asc o desc

            // Alias de sort_by
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

            // Query con joins para ordenar por nombre de relaciones
            $query = Product::with([
                'productLine',
                'productType',
                'productFurniture',
                'productStatus',
                // debo traer el ultimo precio
                'prices' => function ($query) {
                    $query->latest('valid_date_from')->take(1);
                },
                'mainImage',
                'attributeValues.attribute',
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

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('products.name', 'like', '%' . $search . '%')
                        ->orWhere('products.description', 'like', '%' . $search . '%');
                });
            }

            if ($sortKey) {
                $query->orderBy($sortKey, $sortOrder === 'desc' ? 'desc' : 'asc');
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
                'stock' => 'required|integer|min:0',
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
                return ApiResponse::create('Error de validación', 422, $validator->errors(), [
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
            $product = Product::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:50|unique:products,code,' . $product->id,
                'id_product_line' => 'required|integer|exists:product_lines,id',
                'id_product_type' => 'required|integer|exists:product_types,id',
                'id_product_furniture' => 'required|integer|exists:product_furnitures,id',
                'places_cant' => 'nullable|integer|min:0',
                'volume' => 'nullable|numeric|min:0',
                'description' => 'nullable|string',
                'stock' => 'required|integer|min:0',
                'product_stock' => 'nullable|integer|exists:products,id',
                'show_catalog' => 'required|boolean',
                'id_product_status' => 'nullable|integer|exists:product_status,id',
                'main_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'existing_images.*.id' => 'nullable|integer|exists:products_images,id',
                'existing_images.*.image' => [
                    'nullable',
                    function ($attribute, $value, $fail) {
                        // Si es archivo, debe ser imagen válida
                        if (is_file($value)) {
                            if (!in_array($value->getClientOriginalExtension(), ['jpeg', 'jpg', 'png'])) {
                                return $fail('El archivo debe ser una imagen jpeg, jpg o png.');
                            }
                            if ($value->getSize() > 2048 * 1024) {
                                return $fail('El archivo no debe superar los 2MB.');
                            }
                        }
                        // Si es string, debe ser una ruta que empiece con "/storage/"
                        elseif (is_string($value) && !str_starts_with($value, '/storage/')) {
                            return $fail('La cadena de imagen debe ser una ruta válida del almacenamiento.');
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
                return ApiResponse::create('Error de validación', 422, $validator->errors(), [
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
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()], [
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

            // Obtener productos usados
            $usedProductIds = ProductUseStock::distinct()->pluck('id_product')->toArray();

            $products = Product::with(['productStock', 'productUseStock'])
                ->whereIn('id', $usedProductIds)
                ->get();

            $groupedByStock = $products->groupBy(function ($product) {
                return $product->product_stock ?? $product->id;
            });

            $result = collect();

            foreach ($groupedByStock as $stockId => $group) {
                $representativeProduct = $group->firstWhere('id', $stockId) ?? $group->first();

                $stock = $representativeProduct->productStock->stock ?? $representativeProduct->stock;

                $usedStock = [];

                foreach ($dates as $date) {
                    $totalUsed = $group->flatMap(fn($p) => $p->productUseStock)
                        ->filter(fn($use) => $use->date_from <= $date && $use->date_to >= $date)
                        ->sum('quantity');

                    $usedStock[$date] = $totalUsed;
                }

                $result->push([
                    'id' => $representativeProduct->id,
                    'name' => $representativeProduct->name,
                    'code' => $representativeProduct->code,
                    'stock' => $stock,
                    'used_stock_by_day' => $usedStock,
                ]);
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

            // Traer todos los usos de productos en el rango del mes con relaciones
            $productUses = ProductUseStock::where(function ($query) use ($start, $end) {
                $query->whereBetween('date_from', [$start, $end])
                    ->orWhereBetween('date_to', [$start, $end]);
            })
                ->with(['product', 'product.productStock'])
                ->get();

            $result = [];

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $currentDate = $date->toDateString();

                // Filtrar usos válidos para esa fecha
                $usesForDate = $productUses->filter(function ($use) use ($currentDate) {
                    return $use->date_from <= $currentDate && $use->date_to >= $currentDate;
                });

                // Agrupar por presupuesto
                $groupedByBudget = $usesForDate->groupBy('id_budget');

                $budgets = [];

                foreach ($groupedByBudget as $budgetId => $usesInBudget) {
                    // Agrupar por producto y sumar cantidad usada
                    $products = $usesInBudget->groupBy('id_product')->map(function ($group) {
                        $firstUse = $group->first();
                        $product = $firstUse->product;

                        return [
                            'id' => $product->id,
                            'name' => $product->name,
                            'code' => $product->code,
                            'stock' => optional($product->productStock)->stock ?? $product->stock,
                            'used_stock' => $group->sum('quantity'),
                        ];
                    })->values();

                    $budgets[] = [
                        'id_budget' => $budgetId,
                        'products' => $products,
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
                'file_url' => 'storage/reports/' . $fileName,
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
