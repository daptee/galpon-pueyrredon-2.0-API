<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlaceArea extends Model
{
    use HasFactory;

    protected $table = 'places_area'; // Nombre de la tabla en la BD

    protected $fillable = ['name', 'status'];

    public function status()
    {
        return $this->belongsTo(Status::class, 'status');
    }
}
