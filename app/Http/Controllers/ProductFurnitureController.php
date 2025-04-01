<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\ProductFurniture;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Exception;

class ProductFurnitureController extends Controller
{
    // Obtener todas los muebles de productos (sin paginación)
    public function index(Request $request)
    {
        try {
            $products = ProductFurniture::all();

            $products->load(['status']);

            return ApiResponse::create('Listado de muebles de productos obtenido correctamente', 200, $products, [
                'request' => $request,
                'module' => 'product furniture',
                'endpoint' => 'Obtener todas los muebles de productos',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error inesperado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'product furniture',
                'endpoint' => 'Obtener todas los muebles de productos',
            ]);
        }
    }

    // Crear un nuevo mueble de producto
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
                    'module' => 'product furniture',
                    'endpoint' => 'Crear muebles de productos',
                ]);
            }
            
            $productFurniture = ProductFurniture::create($request->all());

            $productFurniture->load(['status']);

            return ApiResponse::create('Mueble de producto creado correctamente', 201, $productFurniture, [
                'request' => $request,
                'module' => 'product furniture',
                'endpoint' => 'Crear mueble de producto',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error inesperado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'product furniture',
                'endpoint' => 'Crear mueble de producto',
            ]);
        }
    }

    // Actualizar un mueble de producto existente
    public function update(Request $request, $id)
    {
        try {
            $productFurniture = ProductFurniture::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'status' => 'sometimes|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validacion', 422, ['error' => $validator->errors()], [
                    'request' => $request,
                    'module' => 'product furniture',
                    'endpoint' => 'Actualizar mueble de producto',
                ]);
            }

            $productFurniture->update($request->all());

            $productFurniture->load(['status']);

            return ApiResponse::create('Mueble de producto actualizado correctamente', 201, $productFurniture, [
                'request' => $request,
                'module' => 'product furniture',
                'endpoint' => 'Actualizar mueble de producto',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error inesperado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'product furniture',
                'endpoint' => 'Actualizar mueble de producto',
            ]);
        }
    }

    // Eliminar un mueble de producto (verificando relaciones)
    public function destroy(Request $request, $id)
    {
        try {
            $productFurniture = ProductFurniture::findOrFail($id);

            // Verificar si hay productos asociados
            if (Product::where('id_product_line', $id)->exists()) {
                return ApiResponse::create('Error de validacion', 422, ['error' => 'No se puede eliminar porque está en uso en productos.'], [
                    'request' => $request,
                    'module' => 'product furniture',
                    'endpoint' => 'Eliminar mueble de producto',
                ]);
            }

            $productFurniture->delete();

            return ApiResponse::create('Mueble de producto eliminado correctamente', 200, $productFurniture, [
                'request' => $request,
                'module' => 'product furniture',
                'endpoint' => 'Eliminar mueble de producto',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error inesperado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'product furniture',
                'endpoint' => 'Eliminar mueble de producto',
            ]);
        }
    }
}
