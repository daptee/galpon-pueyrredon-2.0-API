<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\ProductType;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Exception;

class ProductTypeController extends Controller
{
    // Obtener todas los tipos de productos (sin paginación)
    public function index(Request $request)
    {
        try {
            $products = ProductType::with(['status'])
                    ->orderBy('name')
                    ->get();

            return ApiResponse::create('Listado de tipos de productos obtenido correctamente', 200, $products, [
                'request' => $request,
                'module' => 'product type',
                'endpoint' => 'Obtener todas los tipos de productos',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error inesperado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'product type',
                'endpoint' => 'Obtener todas los tipos de productos',
            ]);
        }
    }

    // Crear un nuevo tipo de producto
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'status' => 'sometimes|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validacion', 422, ['error' => $validator->errors()], [
                    'request' => $request,
                    'module' => 'product type',
                    'endpoint' => 'Crear tipos de productos',
                ]);
            }
            
            $productType = ProductType::create($request->all());

            $productType->load(['status']);

            return ApiResponse::create('Tipo de producto creado correctamente', 201, $productType, [
                'request' => $request,
                'module' => 'product type',
                'endpoint' => 'Crear tipo de producto',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error inesperado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'product type',
                'endpoint' => 'Crear tipo de producto',
            ]);
        }
    }

    // Actualizar un tipo de producto existente
    public function update(Request $request, $id)
    {
        try {
            $productType = ProductType::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'status' => 'sometimes|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validacion', 422, ['error' => $validator->errors()], [
                    'request' => $request,
                    'module' => 'product type',
                    'endpoint' => 'Actualizar tipo de producto',
                ]);
            }

            $productType->update($request->all());

            $productType->load(['status']);

            return ApiResponse::create('Tipo de producto actualizado correctamente', 201, $productType, [
                'request' => $request,
                'module' => 'product type',
                'endpoint' => 'Actualizar tipo de producto',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error inesperado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'product type',
                'endpoint' => 'Actualizar tipo de producto',
            ]);
        }
    }

    // Eliminar un tipo de producto (verificando relaciones)
    public function destroy(Request $request, $id)
    {
        try {
            $productType = ProductType::findOrFail($id);

            // Verificar si hay productos asociados
            if (Product::where('id_product_line', $id)->exists()) {
                return ApiResponse::create('Error de validacion', 422, ['error' => 'No se puede eliminar porque está en uso en productos.'], [
                    'request' => $request,
                    'module' => 'product type',
                    'endpoint' => 'Eliminar tipo de producto',
                ]);
            }

            $productType->delete();

            return ApiResponse::create('Tipo de producto eliminado correctamente', 200, $productType, [
                'request' => $request,
                'module' => 'product type',
                'endpoint' => 'Eliminar tipo de producto',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error inesperado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'product type',
                'endpoint' => 'Eliminar tipo de producto',
            ]);
        }
    }
}
