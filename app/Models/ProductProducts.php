<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductProducts extends Model
{
    protected $fillable = ['id_parent_product', 'id_product', 'quantity'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'id_product');
    }

    public function combo()
    {
        return $this->belongsTo(Product::class, 'id_parent_product');
    }
}
