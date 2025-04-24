<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetProducts extends Model
{
    protected $table = 'budget_products';

    protected $fillable = [
        'id_budget',
        'id_product',
        'quantity',
        'price',
        'has_stock'
    ];

    public $timestamps = true;

    public function product()
    {
        return $this->belongsTo(Product::class, 'id_product');
    }
}
