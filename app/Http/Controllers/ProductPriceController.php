<?php

namespace App\Http\Controllers;

use App\Exports\ProductPricesExport;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelFormat;

class ProductPriceController extends Controller
{
    public function getPricesByDate(Request $request)
    {
        try {
            $date = $request->query('date');
            $sortOrder = $request->query('sort_order', 'asc');
            $perPage = $request->query('per_page');
            $page = $request->query('page', 1);

            // Alias para ordenamiento por relaciones
            $sortAlias = [
                'name' => 'products.name',
                'line' => 'product_lines.name',
                'type' => 'product_types.name',
                'furniture' => 'product_furnitures.name',
            ];
            $sortKey = $sortAlias[$request->query('sort_by')] ?? null;

            if (!$date) {
                return response()->json(['error' => 'Debe proporcionar una fecha'], 400);
            }

            // Armamos el query
            $query = Product::with([
                'productLine',
                'productType',
                'productFurniture',
                'prices' => function ($query) use ($date) {
                    $query->whereDate('valid_date_from', '<=', $date)
                        ->whereDate('valid_date_to', '>=', $date);
                }
            ])
                ->join('product_lines', 'products.id_product_line', '=', 'product_lines.id')
                ->join('product_types', 'products.id_product_type', '=', 'product_types.id')
                ->join('product_furnitures', 'products.id_product_furniture', '=', 'product_furnitures.id')
                ->select('products.*');

            if ($sortKey) {
                $query->orderBy($sortKey, $sortOrder === 'desc' ? 'desc' : 'asc');
            }

            $products = $query->get();

            // Mapear resultados
            $mapped = $products->map(function ($product) {
                $price = $product->prices->first();
                return [
                    'id_product' => $product->id,
                    'name' => $product->name,
                    'code' => $product->code,
                    'line' => $product->productLine->name ?? null,
                    'type' => $product->productType->name ?? null,
                    'furniture' => $product->productFurniture->name ?? null,
                    'vigente_price' => $price ? $price->price : null,
                ];
            });

            $total = $mapped->count();

            if ($perPage) {
                $perPage = (int) $perPage;
                $page = (int) $page;
                $paged = $mapped->forPage($page, $perPage)->values();

                $meta_data = [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage),
                ];
            } else {
                $paged = $mapped;
                $meta_data = [
                    'page' => 1,
                    'per_page' => $total,
                    'total' => $total,
                    'last_page' => 1,
                ];
            }

            return ApiResponse::paginate('Precios obtenidos correctamente', 200, $paged, $meta_data, [
                'request' => $request,
                'module' => 'product price',
                'endpoint' => 'Obtener precios por fecha',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener los precios del producto', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'product price',
                'endpoint' => 'Obtener precios por fecha',
            ]);
        }
    }

    public function exportPricesByDate(Request $request)
    {
        try {
            $date = $request->query('date');
            if (!$date) {
                return response()->json(['error' => 'Debe proporcionar una fecha'], 400);
            }

            $products = Product::with([
                'prices' => function ($query) use ($date) {
                    $query->whereDate('valid_date_from', '<=', $date)
                        ->whereDate('valid_date_to', '>=', $date);
                }
            ])->get();

            $result = $products->map(function ($product) use ($date) {
                $price = $product->prices->first();
                return [
                    'id_product' => $product->id,
                    'name' => $product->name,
                    'code' => $product->code,
                    'vigente_price' => $price ? $price->price : null,
                ];
            });

            $fileName = 'product_prices_' . now()->format('Ymd_His') . '.xlsx';
            $directory = public_path('storage/prices');

            // Crear el directorio si no existe
            if (!file_exists(public_path('storage/prices'))) {
                mkdir(public_path('storage/prices'), 0755, true);
            }
            ;

            $filePath = $directory . '/' . $fileName;

            // Crear y guardar el archivo Excel manualmente en el path deseado
            $writer = Excel::raw(new ProductPricesExport($result), ExcelFormat::XLSX);
            file_put_contents($filePath, $writer);

            return ApiResponse::create('Archivo exportado correctamente', 200, [
                'file_url' => 'storage/prices/' . $fileName,
            ], [
                'request' => $request,
                'module' => 'product price',
                'endpoint' => 'Exportar precios por fecha',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al exportar los precios del producto', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'product price',
                'endpoint' => 'Exportar precios por fecha',
            ]);
        }
    }
}