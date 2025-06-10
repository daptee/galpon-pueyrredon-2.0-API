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
                $price = $product->prices->first(); // puede haber solo un precio vigente para esa fecha
                return [
                    'id_product' => $product->id,
                    'name' => $product->name,
                    'code' => $product->code,
                    'vigente_price' => $price ? $price->price : null,
                ];
            });

            return ApiResponse::create('Precios obtenidos correctamente', 200, $result, [
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
            };

            $filePath = $directory . '/' . $fileName;

            // Crear y guardar el archivo Excel manualmente en el path deseado
            $writer = Excel::raw(new ProductPricesExport($result), ExcelFormat::XLSX);
            file_put_contents($filePath, $writer);
            
            return ApiResponse::create('Archivo exportado correctamente', 200, [
                'file_url' => asset('public/storage/prices/' . $fileName),
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