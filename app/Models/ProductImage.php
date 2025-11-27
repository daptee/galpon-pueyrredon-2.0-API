<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use HasFactory;

    protected $table = 'products_images';

    protected $fillable = [
        'id_product',
        'image',
        'is_main',
    ];

    protected $casts = [
        'is_main' => 'boolean',
    ];

    // Relación con el producto al que pertenece la imagen
    public function product()
    {
        return $this->belongsTo(Product::class, 'id_product');
    }
}
