<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\BudgetProducts;
use App\Models\Product;
use App\Models\ProductProducts;

class BudgetVolumeService
{
    public function calculateBudgetVolume(Budget $budget): float
    {
        $budgetProducts = BudgetProducts::where('id_budget', $budget->id)->get();

        if ($budgetProducts->isEmpty()) {
            return 0;
        }

        $totalVolume = 0;

        foreach ($budgetProducts as $budgetProduct) {
            $product = Product::with(['comboItems.product'])->find($budgetProduct->id_product);

            if (!$product) {
                continue;
            }

            $productVolume = $this->getProductVolume($product);
            $totalVolume += $productVolume * $budgetProduct->quantity;
        }

        return round($totalVolume, 2);
    }

    /**
     * Recalcula el volumen del presupuesto y lo persiste si difiere del valor guardado.
     */
    public function recalculateAndSave(Budget $budget): float
    {
        $totalVolume = $this->calculateBudgetVolume($budget);

        if ($budget->volume != $totalVolume) {
            $budget->volume = $totalVolume;
            $budget->save();
        }

        return $totalVolume;
    }

    private function getProductVolume(Product $product): float
    {
        if ($product->id_product_type == 2) {
            return $this->getComboVolume($product);
        }

        return $product->volume ?? 0;
    }

    private function getComboVolume(Product $comboProduct): float
    {
        $comboItems = ProductProducts::where('id_parent_product', $comboProduct->id)
            ->with('product')
            ->get();

        if ($comboItems->isEmpty()) {
            return 0;
        }

        $totalVolume = 0;

        foreach ($comboItems as $item) {
            $childProduct = $item->product;

            if (!$childProduct) {
                continue;
            }

            if ($childProduct->id_product_type == 2) {
                $childVolume = $this->getComboVolume($childProduct);
            } else {
                $childVolume = $childProduct->volume ?? 0;
            }

            $totalVolume += $childVolume * $item->quantity;
        }

        return $totalVolume;
    }
}
