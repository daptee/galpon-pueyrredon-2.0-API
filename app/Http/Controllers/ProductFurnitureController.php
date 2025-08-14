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
    // Obtener todos los muebles de productos (sin paginación)
    public function index(Request $request)
    {
        try {
            // Parámetros de paginación
            $perPage = $request->query('per_page');
            $page = $request->query('page', 1);

            // Construir la consulta base con relaciones
            $query = ProductFurniture::with('status')
                ->orderBy('name');

            // Aplicar paginación si se especifica per_page
            if ($perPage) {
                $products = $query->paginate($perPage, ['*'], 'page', $page);
                $data = $products->items();
                $meta_data = [
                    'page' => $products->currentPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'last_page' => $products->lastPage(),
                ];
            } else {
                // Si no se especifica per_page, traer todos los registros
                $data = $query->get();
                $meta_data = null;
            }

            return ApiResponse::paginate('Listado de muebles de productos obtenido correctamente', 200, $data, $meta_data, [
                'request' => $request,
                'module' => 'product furniture',
                'endpoint' => 'Obtener todos los muebles de productos',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error inesperado', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'product furniture',
                'endpoint' => 'Obtener todos los muebles de productos',
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
                    'endpoint' => 'Crear mueble de producto',
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
