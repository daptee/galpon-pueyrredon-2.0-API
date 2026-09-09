<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Budget;
use App\Models\LogisticsSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LogisticsSheetController extends Controller
{
    /**
     * Obtiene la ficha logística de un presupuesto, o la crea (con un token
     * nuevo) si todavía no existe. Devuelve el link público para que el
     * sector operativo lo envíe manualmente por mail o WhatsApp.
     */
    public function getOrCreate(Request $request, $idBudget)
    {
        try {
            $budget = Budget::find($idBudget);

            if (!$budget) {
                return ApiResponse::create('Presupuesto no encontrado', 404, ['error' => 'Presupuesto no encontrado'], [
                    'request' => $request,
                    'module' => 'logistics sheet',
                    'endpoint' => 'Obtener o crear ficha logística',
                ]);
            }

            $logisticsSheet = LogisticsSheet::firstOrCreate(
                ['id_budget' => $idBudget],
                ['token' => (string) Str::uuid()]
            );

            $logisticsSheet->load('budget', 'eventType');

            $result = $logisticsSheet->toArray();
            $result['public_url'] = rtrim(env('LOGISTICS_SHEET_FRONTEND_URL', ''), '/') . '/ficha-logistica/' . $logisticsSheet->token;

            return ApiResponse::create('Ficha logística obtenida correctamente', 200, $result, [
                'request' => $request,
                'module' => 'logistics sheet',
                'endpoint' => 'Obtener o crear ficha logística',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener la ficha logística', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'logistics sheet',
                'endpoint' => 'Obtener o crear ficha logística',
            ]);
        }
    }
}
