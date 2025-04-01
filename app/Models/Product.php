<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'name',
        'code',
        'id_product_line',
        'id_product_type',
        'id_product_furniture',
        'places_cant',
        'volume',
        'description',
        'stock',
        'product_stock',
        'show_catalog',
        'id_product_status'
    ];

    public $timestamps = true;
}
