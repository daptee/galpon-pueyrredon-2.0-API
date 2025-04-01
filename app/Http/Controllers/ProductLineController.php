<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\ProductLine;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;

class ProductLineController extends Controller
{
    // Obtener todas las líneas de productos (sin paginación)
    public function index(Request $request)
    {
        $products = ProductLine::all();

        $products->load(['status']);

        return ApiResponse::create('Listado de lineas de productos obtenido correctamente', 200, $products, [
            'request' => $request,
            'module' => 'product line',
            'endpoint' => 'Obtener todas las lineas de productos',
        ]);
    }

    // Crear una nueva línea de producto
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'status' => 'sometimes|in:1,2,3',
        ]);

        if ($validator->fails()) {
            return ApiResponse::create('Error de validacion', 422, ['error' => $validator->errors()], [
                'request' => $request,
                'module' => 'product line',
                'endpoint' => 'Crear linea de productos',
            ]);
        }
        
        $productLine = ProductLine::create($request->all());

        $productLine->load(['status']);

        return ApiResponse::create('Linea de productos creada correctamente', 201, $productLine, [
            'request' => $request,
            'module' => 'product line',
            'endpoint' => 'Crear linea de productos',
        ]);
    }

    // Actualizar una línea de producto existente
    public function update(Request $request, $id)
    {
        $productLine = ProductLine::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'status' => 'sometimes|in:1,2,3',
        ]);

        if ($validator->fails()) {
            return ApiResponse::create('Error de validacion', 422, ['error' => $validator->errors()], [
                'request' => $request,
                'module' => 'product line',
                'endpoint' => 'Actualizar linea de productos',
            ]);
        }

        $productLine->update($request->all());

        $productLine->load(['status']);

        return ApiResponse::create('Linea de productos actualizado correctamente', 201, $productLine, [
            'request' => $request,
            'module' => 'product line',
            'endpoint' => 'Actualizar linea de productos',
        ]);
    }

    // Eliminar una línea de producto (verificando relaciones)
    public function destroy(Request $request, $id)
    {
        $productLine = ProductLine::findOrFail($id);

        // Verificar si hay productos asociados
        if (Product::where('id_product_line', $id)->exists()) {
            return ApiResponse::create('Error de validacion', 422, ['error' => 'No se puede eliminar porque está en uso en productos.'], [
                'request' => $request,
                'module' => 'product line',
                'endpoint' => 'Eliminar linea de productos',
            ]);
        }

        $productLine->delete();

        return ApiResponse::create('Linea de productos eliminado correctamente', 200, $productLine, [
            'request' => $request,
            'module' => 'product line',
            'endpoint' => 'Eliminar linea de productos',
        ]);
    }
}
