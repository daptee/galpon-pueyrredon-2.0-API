<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductUseStock extends Model
{
    use HasFactory;

    protected $table = 'products_use_stock';

    protected $fillable = [
        'id_budget',
        'id_product',
        'id_product_stock',
        'date_from',
        'date_to',
        'quantity'
    ];

    public $timestamps = true;

    public function product()
    {
        return $this->belongsTo(Product::class, 'id_product');
    }
}