<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model
{
    use HasFactory;

    protected $table = 'product_prices';

    protected $fillable = [
        'id_product',
        'price',
        'valid_date_from',
        'valid_date_to',
        'minimun_quantity',
        'client_bonification',
        'id_bulk_update',
    ];

    protected $casts = [
        'valid_date_from' => 'datetime',
        'valid_date_to' => 'datetime',
        'client_bonification' => 'boolean',
    ];

    // Relación con el producto
    public function product()
    {
        return $this->belongsTo(Product::class, 'id_product');
    }
}
