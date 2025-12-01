<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Budget;
use App\Models\BudgetProducts;
use App\Models\Product;
use App\Models\ProductProducts;
use App\Models\ProductPrice;
use Carbon\Carbon;

class CheckBudgetProductsPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'budget:check-prices {--budget_id= : ID específico del presupuesto a verificar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica si los productos de los presupuestos tienen precios disponibles para la fecha del evento y actualiza las columnas has_price en budget_products y products_has_prices en budgets';

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

        $this->info("Verificando precios para " . $budgets->count() . " presupuesto(s)...");

        $updatedBudgets = 0;
        $updatedBudgetProducts = 0;
        $bar = $this->output->createProgressBar($budgets->count());

        foreach ($budgets as $budget) {
            $allProductsHavePrice = $this->checkAndUpdateBudgetProductPrices($budget, $updatedBudgetProducts);

            // Actualizar la columna products_has_prices en el presupuesto
            if ($budget->products_has_prices !== $allProductsHavePrice) {
                $budget->products_has_prices = $allProductsHavePrice;
                $budget->save();
                $updatedBudgets++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Proceso completado.");
        $this->info("Se actualizaron {$updatedBudgets} presupuesto(s).");
        $this->info("Se actualizaron {$updatedBudgetProducts} productos de presupuestos.");

        return 0;
    }

    /**
     * Verifica y actualiza el estado de precios de todos los productos de un presupuesto
     *
     * @param Budget $budget
     * @param int &$updatedCount
     * @return bool True si todos los productos tienen precio
     */
    private function checkAndUpdateBudgetProductPrices(Budget $budget, &$updatedCount): bool
    {
        if (!$budget->date_event) {
            return false;
        }

        $budgetProducts = BudgetProducts::where('id_budget', $budget->id)->get();

        if ($budgetProducts->isEmpty()) {
            return false;
        }

        $allHavePrice = true;
        $eventDate = Carbon::parse($budget->date_event);

        foreach ($budgetProducts as $budgetProduct) {
            $product = Product::find($budgetProduct->id_product);

            if (!$product) {
                $allHavePrice = false;
                $this->updateBudgetProductPrice($budgetProduct, false, $updatedCount);
                continue;
            }

            $hasPrice = $this->checkProductHasPrice($product, $eventDate);

            // Actualizar has_price en budget_products
            $this->updateBudgetProductPrice($budgetProduct, $hasPrice, $updatedCount);

            if (!$hasPrice) {
                $allHavePrice = false;
            }
        }

        return $allHavePrice;
    }

    /**
     * Verifica si un producto tiene precio disponible para una fecha
     * Considera si es un producto individual o combo
     *
     * @param Product $product
     * @param Carbon $eventDate
     * @return bool
     */
    private function checkProductHasPrice(Product $product, Carbon $eventDate): bool
    {
        // Si es un combo (id_product_type == 2)
        if ($product->id_product_type == 2) {
            return $this->checkComboHasPrice($product, $eventDate);
        }

        // Si es un producto individual, verificar su precio
        return $this->checkSingleProductPrice($product->id, $eventDate);
    }

    /**
     * Verifica si un producto individual tiene precio válido para la fecha
     *
     * @param int $productId
     * @param Carbon $eventDate
     * @return bool
     */
    private function checkSingleProductPrice(int $productId, Carbon $eventDate): bool
    {
        $price = ProductPrice::where('id_product', $productId)
            ->where('valid_date_from', '<=', $eventDate)
            ->where('valid_date_to', '>=', $eventDate)
            ->first();

        return $price !== null;
    }

    /**
     * Verifica si todos los productos de un combo tienen precio válido
     *
     * @param Product $comboProduct
     * @param Carbon $eventDate
     * @return bool
     */
    private function checkComboHasPrice(Product $comboProduct, Carbon $eventDate): bool
    {
        $comboItems = ProductProducts::where('id_parent_product', $comboProduct->id)
            ->with('product')
            ->get();

        if ($comboItems->isEmpty()) {
            return false;
        }

        foreach ($comboItems as $item) {
            $childProduct = $item->product;

            if (!$childProduct) {
                return false;
            }

            // Si el producto hijo también es un combo, verificar recursivamente
            if ($childProduct->id_product_type == 2) {
                if (!$this->checkComboHasPrice($childProduct, $eventDate)) {
                    return false;
                }
            } else {
                // Verificar precio del producto individual
                if (!$this->checkSingleProductPrice($childProduct->id, $eventDate)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Actualiza la columna has_price en budget_products si ha cambiado
     *
     * @param BudgetProducts $budgetProduct
     * @param bool $hasPrice
     * @param int &$updatedCount
     */
    private function updateBudgetProductPrice(BudgetProducts $budgetProduct, bool $hasPrice, &$updatedCount): void
    {
        if ($budgetProduct->has_price !== $hasPrice) {
            $budgetProduct->has_price = $hasPrice;
            $budgetProduct->save();
            $updatedCount++;
        }
    }
}
