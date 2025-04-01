<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductType extends Model
{
    use HasFactory;

    protected $table = 'product_types';

    protected $fillable = ['name', 'status'];

    public $timestamps = true;

    public function status()
    {
        return $this->belongsTo(Status::class, 'status');
    }
}
