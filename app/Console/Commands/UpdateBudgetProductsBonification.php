<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Budget;
use App\Models\BudgetProducts;
use App\Models\Product;
use App\Models\ProductProducts;
use App\Models\ProductPrice;
use Carbon\Carbon;

class UpdateBudgetProductsBonification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'budget:update-bonification {--budget_id= : ID específico del presupuesto a actualizar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza la columna client_bonification en budget_products basándose en el precio vigente para la fecha del evento';

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

        $this->info("Actualizando client_bonification para " . $budgets->count() . " presupuesto(s)...");

        $updated = 0;
        $bar = $this->output->createProgressBar($budgets->count());

        foreach ($budgets as $budget) {
            $updatedCount = $this->updateBudgetProductsBonification($budget);
            $updated += $updatedCount;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Proceso completado. Se actualizaron {$updated} producto(s) de presupuestos.");

        return 0;
    }

    /**
     * Actualiza el client_bonification de todos los productos de un presupuesto
     *
     * @param Budget $budget
     * @return int Cantidad de productos actualizados
     */
    private function updateBudgetProductsBonification(Budget $budget): int
    {
        if (!$budget->date_event) {
            return 0;
        }

        $budgetProducts = BudgetProducts::where('id_budget', $budget->id)->get();

        if ($budgetProducts->isEmpty()) {
            return 0;
        }

        $eventDate = Carbon::parse($budget->date_event);
        $updatedCount = 0;

        foreach ($budgetProducts as $budgetProduct) {
            $product = Product::find($budgetProduct->id_product);

            if (!$product) {
                continue;
            }

            $clientBonification = $this->getProductBonification($product, $eventDate);

            // Actualizar solo si el valor cambió
            if ($budgetProduct->client_bonification !== $clientBonification) {
                $budgetProduct->client_bonification = $clientBonification;
                $budgetProduct->save();
                $updatedCount++;
            }
        }

        return $updatedCount;
    }

    /**
     * Obtiene el valor de client_bonification para un producto en una fecha específica
     * Si es un combo, obtiene el valor del primer producto componente que tenga precio
     *
     * @param Product $product
     * @param Carbon $eventDate
     * @return bool
     */
    private function getProductBonification(Product $product, Carbon $eventDate): bool
    {
        // Si es un combo (id_product_type == 2)
        if ($product->id_product_type == 2) {
            return $this->getComboBonification($product, $eventDate);
        }

        // Si es un producto individual, obtener su bonificación del precio vigente
        return $this->getSingleProductBonification($product->id, $eventDate);
    }

    /**
     * Obtiene el client_bonification de un producto individual basado en su precio vigente
     *
     * @param int $productId
     * @param Carbon $eventDate
     * @return bool
     */
    private function getSingleProductBonification(int $productId, Carbon $eventDate): bool
    {
        $price = ProductPrice::where('id_product', $productId)
            ->where('valid_date_from', '<=', $eventDate)
            ->where('valid_date_to', '>=', $eventDate)
            ->first();

        return $price ? (bool) $price->client_bonification : false;
    }

    /**
     * Obtiene el client_bonification de un combo
     * Toma el valor del primer producto componente que tenga precio
     *
     * @param Product $comboProduct
     * @param Carbon $eventDate
     * @return bool
     */
    private function getComboBonification(Product $comboProduct, Carbon $eventDate): bool
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
                continue;
            }

            // Si el producto hijo también es un combo, verificar recursivamente
            if ($childProduct->id_product_type == 2) {
                $bonification = $this->getComboBonification($childProduct, $eventDate);
                if ($bonification !== false) {
                    return $bonification;
                }
            } else {
                // Obtener bonificación del producto individual
                $bonification = $this->getSingleProductBonification($childProduct->id, $eventDate);
                // Retornar el primer valor encontrado
                return $bonification;
            }
        }

        return false;
    }
}
