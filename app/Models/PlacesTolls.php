<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlacesTolls extends Model
{
    use HasFactory;

    protected $table = 'places_tolls'; // Nombre exacto de la tabla

    protected $fillable = ['id_place', 'id_toll', 'status'];

    public $timestamps = true;
}
