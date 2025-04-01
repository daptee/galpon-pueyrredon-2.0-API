<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductStatus extends Model
{
    use HasFactory;

    protected $table = 'product_status';

    protected $fillable = [
        'name',
    ];

    // Relación con los productos que tienen este estado
    public function products()
    {
        return $this->hasMany(Product::class, 'id_product_status');
    }
}
