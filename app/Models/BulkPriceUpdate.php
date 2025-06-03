<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BulkPriceUpdate extends Model
{
    protected $fillable = ['from_date', 'to_date', 'percentage'];

    public function productPrices()
    {
        return $this->hasMany(ProductPrice::class, 'id_bulk_update');
    }
}