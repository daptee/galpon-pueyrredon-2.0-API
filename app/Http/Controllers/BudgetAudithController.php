<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Budget;
use App\Models\BudgetAudith;
use Illuminate\Http\Request;

class BudgetAudithController extends Controller
{
    public function index($id)
    {
        try {
            $audith = BudgetAudith::where('id_budget', $id)
            ->orWhereIn('id_budget', Budget::where('id_budget', $id)->pluck('id'))
            ->get();

            return ApiResponse::create('Auditoria del presupuesto obtenida correctamente', 200, $audith, []);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener la auditoria del presupuesto', 500, ['error' => $e->getMessage()], []);
        }
    }
}
