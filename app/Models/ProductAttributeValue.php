<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductAttributeValue extends Model
{
    use HasFactory;

    protected $table = 'product_attributes_values';

    protected $fillable = [
        'id_product',
        'id_product_attribute',
        'value'
    ];

    protected $hidden = [
        'id_product_attribute'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'id_product');
    }

    public function attribute()
    {
        return $this->belongsTo(ProductAttribute::class, 'id_product_attribute');
    }
}


