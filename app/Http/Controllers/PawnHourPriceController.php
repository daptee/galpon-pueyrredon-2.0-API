<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use App\Models\PawnHourPrice;
use Log;
use Validator;

class PawnHourPriceController extends Controller
{
    // GET ALL - Retorna todos los registros, filtrando por estado si se proporciona
    public function index(Request $request)
    {
        try {

            $pawnHourPrices = PawnHourPrice::get();

            $pawnHourPrices->load(['status']);

            return ApiResponse::create('Precios de hora de peón obtenidos correctamente', 200, $pawnHourPrices, [
                'request' => $request,
                'module' => 'pawn hour prices',
                'endpoint' => 'Obtener todos los precios de hora de peón',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener los precios', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'pawn hour prices',
                'endpoint' => 'Obtener todos los precios de hora de peón',
            ]);
        }
    }

    // POST - Crear un nuevo registro
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'price' => 'required|numeric|min:0',
                'status' => 'nullable|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()], [
                    'request' => $request,
                    'module' => 'pawn hour prices',
                    'endpoint' => 'Crear precio de hora de peón',
                ]);
            }

            $pawnHourPrice = PawnHourPrice::create([
                'price' => $request->price,
                'status' => $request->status ?? 1,
            ]);

            $pawnHourPrice->load(['status']);

            return ApiResponse::create('Precio de hora de peón creado correctamente', 201, $pawnHourPrice, [
                'request' => $request,
                'module' => 'pawn hour prices',
                'endpoint' => 'Crear precio de hora de peón',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al crear el precio', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'pawn hour prices',
                'endpoint' => 'Crear precio de hora de peón',
            ]);
        }
    }

    // PUT - Actualizar un registro
    public function update(Request $request, $id)
    {
        try {
            $pawnHourPrice = PawnHourPrice::find($id);

            if (!$pawnHourPrice) {
                return ApiResponse::create('Precio no encontrado', 404, ['error' => 'Precio no encontrado'], [
                    'request' => $request,
                    'module' => 'pawn hour prices',
                    'endpoint' => 'Actualizar precio de hora de peón',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'price' => 'sometimes|required|numeric|min:0',
                'status' => 'nullable|in:1,2,3',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()], [
                    'request' => $request,
                    'module' => 'pawn hour prices',
                    'endpoint' => 'Actualizar precio de hora de peón',
                ]);
            }

            $pawnHourPrice->update($request->only(['price', 'status']));

            $pawnHourPrice->load(['status']);

            return ApiResponse::create('Precio de hora de peón actualizado correctamente', 200, $pawnHourPrice, [
                'request' => $request,
                'module' => 'pawn hour prices',
                'endpoint' => 'Actualizar precio de hora de peón',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar el precio', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'pawn hour prices',
                'endpoint' => 'Actualizar precio de hora de peón',
            ]);
        }
    }
}
