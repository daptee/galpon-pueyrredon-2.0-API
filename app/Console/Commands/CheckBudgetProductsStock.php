<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Budget;
use App\Models\BudgetProducts;
use App\Models\ProductUseStock;
use App\Models\Product;
use Carbon\Carbon;

class CheckBudgetProductsStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'budget:check-stock {--budget_id= : ID específico del presupuesto a verificar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica si los productos de los presupuestos tienen stock disponible para la fecha del evento y actualiza la columna products_has_stock';

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
            // Obtener todos los presupuestos que tienen fecha de evento
            $budgets = Budget::whereNotNull('date_event')->get();
        }

        $this->info("Verificando stock para " . $budgets->count() . " presupuesto(s)...");

        $updated = 0;
        $bar = $this->output->createProgressBar($budgets->count());

        foreach ($budgets as $budget) {
            $hasStock = $this->checkBudgetStock($budget);

            if ($budget->products_has_stock !== $hasStock) {
                $budget->products_has_stock = $hasStock;
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
     * Verifica si todos los productos del presupuesto tienen stock disponible
     *
     * @param Budget $budget
     * @return bool
     */
    private function checkBudgetStock(Budget $budget): bool
    {
        if (!$budget->date_event || !$budget->days) {
            return false;
        }

        $budgetProducts = BudgetProducts::where('id_budget', $budget->id)->get();

        if ($budgetProducts->isEmpty()) {
            return false;
        }

        $dateFrom = Carbon::parse($budget->date_event);
        $dateTo = Carbon::parse($budget->date_event)->addDays($budget->days - 1);

        foreach ($budgetProducts as $budgetProduct) {
            $product = Product::find($budgetProduct->id_product);

            if (!$product) {
                return false;
            }

            // Determinar el producto de stock a verificar
            $stockProductId = $product->product_stock ?? $product->id;

            // Obtener el stock total del producto
            $totalStock = Product::find($stockProductId)->stock ?? 0;

            if ($totalStock === 0) {
                return false;
            }

            // Calcular el stock usado en el rango de fechas del evento
            $usedStock = $this->getMaxUsedStockInDateRange($stockProductId, $dateFrom, $dateTo, $budget->id);

            // Calcular stock disponible
            $availableStock = $totalStock - $usedStock;

            // Verificar si hay suficiente stock para la cantidad solicitada
            if ($availableStock < $budgetProduct->quantity) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtiene el máximo stock usado en un rango de fechas para un producto
     *
     * @param int $productId
     * @param Carbon $dateFrom
     * @param Carbon $dateTo
     * @param int|null $excludeBudgetId
     * @return int
     */
    private function getMaxUsedStockInDateRange($productId, Carbon $dateFrom, Carbon $dateTo, $excludeBudgetId = null): int
    {
        // Obtener todos los usos de stock que se solapan con el rango de fechas
        $query = ProductUseStock::where('id_product_stock', $productId)
            ->where(function ($q) use ($dateFrom, $dateTo) {
                // Condición 1: El uso comienza dentro del rango
                $q->whereBetween('date_from', [$dateFrom, $dateTo])
                  // Condición 2: El uso termina dentro del rango
                  ->orWhereBetween('date_to', [$dateFrom, $dateTo])
                  // Condición 3: El uso contiene completamente el rango
                  ->orWhere(function ($q2) use ($dateFrom, $dateTo) {
                      $q2->where('date_from', '<=', $dateFrom)
                         ->where('date_to', '>=', $dateTo);
                  });
            });

        // Excluir el presupuesto actual si se proporciona (para no contar su propio uso)
        if ($excludeBudgetId) {
            $query->where('id_budget', '!=', $excludeBudgetId);
        }

        $usedStocks = $query->get();

        if ($usedStocks->isEmpty()) {
            return 0;
        }

        // Agrupar por fecha y calcular el uso máximo en cualquier día
        $maxUsed = 0;

        // Para cada día en el rango, calcular cuánto stock se usa
        $currentDate = $dateFrom->copy();
        while ($currentDate <= $dateTo) {
            $dailyUsed = 0;

            foreach ($usedStocks as $used) {
                $usedFrom = Carbon::parse($used->date_from);
                $usedTo = Carbon::parse($used->date_to);

                // Si este uso de stock incluye la fecha actual
                if ($currentDate >= $usedFrom && $currentDate <= $usedTo) {
                    $dailyUsed += $used->quantity;
                }
            }

            $maxUsed = max($maxUsed, $dailyUsed);
            $currentDate->addDay();
        }

        return $maxUsed;
    }
}
