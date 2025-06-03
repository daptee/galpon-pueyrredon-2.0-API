<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\BulkPriceUpdate;
use App\Models\ProductPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BulkPriceUpdateController extends Controller
{
    public function store(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'percentage' => 'required|numeric',
            'products' => 'required|array',
            'products.*.id_product' => 'required|exists:products,id',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.client_bonification' => 'sometimes|boolean',
            'products.*.minimun_quantity' => 'sometimes|integer',
        ]);

        if ($validated->fails()) {
            return ApiResponse::create('Validación fallida', 422, $validated->errors(), [
                'request' => $request,
                'module' => 'bulk price update',
                'endpoint' => 'Crear actualización masiva de precios',
            ]);
        };
        

        DB::beginTransaction();
        try {
            $bulk = BulkPriceUpdate::create([
                'from_date' => $request->input('from_date'),
                'to_date' => $request->input('to_date'),
                'percentage' => $request->input('percentage'),
            ]);

            foreach ($request->input('products') as $product) {
                ProductPrice::create([
                    'id_product' => $product['id_product'],
                    'price' => $product['price'],
                    'valid_date_from' => $request->input('from_date'),
                    'valid_date_to' => $request->input('to_date'),
                    'minimun_quantity' => $product['minimun_quantity'] ?? 1,
                    'client_bonification' => $product['client_bonification'] ?? true,
                    'id_bulk_update' => $bulk->id,
                ]);
            }

            DB::commit();

            return ApiResponse::create('Actualización masiva de precios creada correctamente', 201, $bulk, [
                'request' => $request,
                'module' => 'bulk price update',
                'endpoint' => 'Crear actualización masiva de precios',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::create('Error al crear la actualización masiva de precios', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'bulk price update',
                'endpoint' => 'Crear actualización masiva de precios',
            ]);
        }
    }

    // Por ultimo, hacer una peticion que permita eliminar todos los precios de una carga masiva que se haya hecho. Esto debe eliminar el registro de esa carga masiva del historial y eliminar todos los precios de productos de esa carga de la tabla product_prices

    public function destroy($id)
    {
        $bulkUpdate = BulkPriceUpdate::findOrFail($id);

        DB::beginTransaction();
        try {
            // Eliminar los precios asociados a la carga masiva
            ProductPrice::where('id_bulk_update', $bulkUpdate->id)->delete();

            // Eliminar la carga masiva
            $bulkUpdate->delete();

            DB::commit();

            return ApiResponse::create('Carga masiva de precios eliminada correctamente', 200, null, [
                'module' => 'bulk price update',
                'endpoint' => 'Eliminar carga masiva de precios',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::create('Error al eliminar la carga masiva de precios', 500, ['error' => $e->getMessage()], [
                'module' => 'bulk price update',
                'endpoint' => 'Eliminar carga masiva de precios',
            ]);
        }
    }
}