<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\ProductAttribute;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Exception;

class ProductAttributeController extends Controller
{
    // Obtener todos los atributos de productos (sin paginación)
    public function index(Request $request)
    {
        try {
            $products = ProductAttribute::all();

            $products->load(['status']);

            return ApiResponse::create('Listado de atributos de productos obtenido correctamente', 200, $products, [
                'request' => $request,
                'module' => 'product attribute',
                'endpoint' => 'Obtener todos los atributos de productos',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error inesperado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'product attribute',
                'endpoint' => 'Obtener todos los atributos de productos',
            ]);
        }
    }

    // Crear un nuevo atributo de producto
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
                    'module' => 'product attribute',
                    'endpoint' => 'Crear atributo de producto',
                ]);
            }
            
            $productAttribute = ProductAttribute::create($request->all());

            $productAttribute->load(['status']);

            return ApiResponse::create('Atributo de producto creado correctamente', 201, $productAttribute, [
                'request' => $request,
                'module' => 'product attribute',
                'endpoint' => 'Crear atributo de producto',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error inesperado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'product attribute',
                'endpoint' => 'Crear atributo de producto',
            ]);
        }
    }

    // Actualizar un atributo de producto existente
    public function update(Request $request, $id)
    {
        try {
            $productAttribute = ProductAttribute::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'status' => 'sometimes|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validacion', 422, ['error' => $validator->errors()], [
                    'request' => $request,
                    'module' => 'product attribute',
                    'endpoint' => 'Actualizar atributo de producto',
                ]);
            }

            $productAttribute->update($request->all());

            $productAttribute->load(['status']);

            return ApiResponse::create('Atributo de producto actualizado correctamente', 201, $productAttribute, [
                'request' => $request,
                'module' => 'product attribute',
                'endpoint' => 'Actualizar atributo de producto',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error inesperado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'product attribute',
                'endpoint' => 'Actualizar atributo de producto',
            ]);
        }
    }

    // Eliminar un atributo de producto (verificando relaciones)
    public function destroy(Request $request, $id)
    {
        try {
            $productAttribute = ProductAttribute::findOrFail($id);

            // Verificar si hay productos asociados
            if (Product::where('id_product_line', $id)->exists()) {
                return ApiResponse::create('Error de validacion', 422, ['error' => 'No se puede eliminar porque está en uso en productos.'], [
                    'request' => $request,
                    'module' => 'product attribute',
                    'endpoint' => 'Eliminar atributo de producto',
                ]);
            }

            $productAttribute->delete();

            return ApiResponse::create('Atributo de producto eliminado correctamente', 200, $productAttribute, [
                'request' => $request,
                'module' => 'product attribute',
                'endpoint' => 'Eliminar atributo de producto',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error inesperado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'product attribute',
                'endpoint' => 'Eliminar atributo de producto',
            ]);
        }
    }
}
