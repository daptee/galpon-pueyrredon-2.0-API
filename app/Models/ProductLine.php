<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductLine extends Model
{
    use HasFactory;

    protected $table = 'product_lines';

    protected $fillable = ['name', 'status'];

    public $timestamps = true;
}
