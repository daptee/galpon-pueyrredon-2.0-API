<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Budget;
use App\Models\BudgetProducts;
use App\Models\Product;
use App\Models\ProductProducts;

class CalculateBudgetVolume extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'budget:calculate-volume {--budget_id= : ID específico del presupuesto a calcular}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calcula y actualiza el volumen total de los productos en cada presupuesto, considerando productos individuales y combos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $budgetId = $this->option('budget_id');

        if ($budgetId) {
            $budgets = Budget::where('id', $budgetId)->get();
            if ($budgets->isEmpty()) {
                $this->error("No se encontró el presupuesto con ID: {$budgetId}");
                return 1;
            }
        } else {
            // Obtener todos los presupuestos
            $budgets = Budget::all();
        }

        $this->info("Calculando volumen para " . $budgets->count() . " presupuesto(s)...");

        $updated = 0;
        $bar = $this->output->createProgressBar($budgets->count());

        foreach ($budgets as $budget) {
            $totalVolume = $this->calculateBudgetVolume($budget);

            if ($budget->volume !== $totalVolume) {
                $budget->volume = $totalVolume;
                $budget->save();
                $updated++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Proceso completado. Se actualizaron {$updated} presupuesto(s).");

        return 0;
    }

    /**
     * Calcula el volumen total de un presupuesto
     *
     * @param Budget $budget
     * @return float
     */
    private function calculateBudgetVolume(Budget $budget): float
    {
        $budgetProducts = BudgetProducts::where('id_budget', $budget->id)->get();

        if ($budgetProducts->isEmpty()) {
            return 0;
        }

        $totalVolume = 0;

        foreach ($budgetProducts as $budgetProduct) {
            $product = Product::with(['productType', 'comboItems.product'])->find($budgetProduct->id_product);

            if (!$product) {
                continue;
            }

            // Calcular volumen del producto considerando la cantidad
            $productVolume = $this->getProductVolume($product);
            $totalVolume += $productVolume * $budgetProduct->quantity;
        }

        return round($totalVolume, 2);
    }

    /**
     * Obtiene el volumen de un producto (individual o combo)
     *
     * @param Product $product
     * @return float
     */
    private function getProductVolume(Product $product): float
    {
        // Si es un combo (id_product_type == 2)
        if ($product->id_product_type == 2) {
            return $this->getComboVolume($product);
        }

        // Si es un producto individual, devolver su volumen directamente
        return $product->volume ?? 0;
    }

    /**
     * Calcula el volumen total de un producto combo
     * sumando el volumen de todos sus productos componentes
     *
     * @param Product $comboProduct
     * @return float
     */
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

            // Si el producto hijo también es un combo, calcular recursivamente
            if ($childProduct->id_product_type == 2) {
                $childVolume = $this->getComboVolume($childProduct);
            } else {
                $childVolume = $childProduct->volume ?? 0;
            }

            // Multiplicar por la cantidad de este producto en el combo
            $totalVolume += $childVolume * $item->quantity;
        }

        return $totalVolume;
    }
}
