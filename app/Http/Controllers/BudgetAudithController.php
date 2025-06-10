<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Budget;
use App\Models\BudgetAudith;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BudgetAudithController extends Controller
{
    public function index($id)
    {
        try {
            $budget = Budget::with('parent', 'children')->findOrFail($id);

            Log::info('Presupuesto encontrado: ' . $budget);

            // Obtener el presupuesto raíz (el más arriba del árbol)
            $root = $this->getRootBudget($budget->load('parent'));

            // Obtener todos los presupuestos del árbol completo (padre -> hijos recursivamente)
            $allIds = collect([$root->id]);
            $allIds = $allIds->merge($this->getAllChildrenIds($root->load('children')));

            // Obtener auditorías de todos los presupuestos en el árbol
            $audiths = BudgetAudith::whereIn('id_budget', $allIds)
                ->orderByRaw("FIELD(id_budget, " . $allIds->implode(',') . ")")
                ->get();

            return ApiResponse::create('Auditoría del presupuesto obtenida correctamente', 200, $audiths, []);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al obtener la auditoría del presupuesto', 500, ['error' => $e->getMessage()], []);
        }
    }

    private function getAllChildrenIds($budget)
    {
        $ids = collect();

        foreach ($budget->children as $child) {
            $ids->push($child->id);
            $ids = $ids->merge($this->getAllChildrenIds($child->load('children')));
        }

        return $ids;
    }

    private function getRootBudget($budget)
    {
        while ($budget->parent) {
            $budget = $budget->parent->load('parent');
        }
        return $budget;
    }
}
