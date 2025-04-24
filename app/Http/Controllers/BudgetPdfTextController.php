<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\BudgetPdfText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BudgetPdfTextController extends Controller
{

    public function index(Request $request)
    {
        try {
            $budgetStatus = BudgetPdfText::all();

            return ApiResponse::create('Texto en el PDF del presupuesto traídos correctamente', 200, $budgetStatus, [
                'request' => $request,
                'module' => 'budget pdf text',
                'endpoint' => 'Obtener texto en PDF del presupuesto',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener el texto en el PDF del presupuesto', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'budget pdf text',
                'endpoint' => 'Obtener texto en PDF del presupuesto',
            ]);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $pdfText = BudgetPdfText::find($id);

            if (!$pdfText) {
                return ApiResponse::create('Texto del PDF no encontrado', 404, ['error' => 'Texto del PDF no encontrado'], [
                    'request' => $request,
                    'module' => 'budget pdf text',
                    'endpoint' => 'Actualizar texto del PDF',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'payment_method' => 'sometimes|required|string',
                'security_deposit' => 'sometimes|required|string',
                'validity_days' => 'sometimes|required|integer|min:1',
                'warnings' => 'sometimes|required|string',
                'no_price_products' => 'sometimes|required|string',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()], [
                    'request' => $request,
                    'module' => 'budget pdf text',
                    'endpoint' => 'Actualizar texto del PDF',
                ]);
            }

            $pdfText->update($request->only([
                'payment_method',
                'security_deposit',
                'validity_days',
                'warnings',
                'no_price_products'
            ]));

            return ApiResponse::create('Texto del PDF actualizado correctamente', 201, $pdfText, [
                'request' => $request,
                'module' => 'budget pdf text',
                'endpoint' => 'Actualizar texto del PDF',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar el texto del PDF', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'budget pdf text',
                'endpoint' => 'Actualizar texto del PDF',
            ]);
        }
    }
}
