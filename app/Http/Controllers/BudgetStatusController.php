<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\BudgetStatus;
use Illuminate\Http\Request;

class BudgetStatusController extends Controller
{
    public function index(Request $request)
    {
        try {
            $budgetStatus = BudgetStatus::all();
    
            return ApiResponse::create('Estado del presupuesto traídos correctamente', 200, $budgetStatus, [
                'request' => $request,
                'module' => 'budget status',
                'endpoint' => 'Obtener estado del presupuesto',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener el estado del presupuesto', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'budget status',
                'endpoint' => 'Obtener estado del presupuesto',
            ]);
        }
    }
}
