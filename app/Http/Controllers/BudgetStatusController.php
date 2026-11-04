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
            $budgetStatus = BudgetStatus::orderBy('name')->get();
    
            return ApiResponse::create('Estado del presupuesto traídos correctamente', 200, $budgetStatus, [
                'request' => $request,
                'module' => 'budget status',
                'endpoint' => 'Obtener estado del presupuesto',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::exception('Error al obtener el estado del presupuesto', $e, [
                'request' => $request,
                'module' => 'budget status',
                'endpoint' => 'Obtener estado del presupuesto',
            ]);
        }
    }
}
