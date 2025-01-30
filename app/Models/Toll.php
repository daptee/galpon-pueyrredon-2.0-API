<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Toll extends Model
{
    use HasFactory;

    protected $table = 'tolls';

    protected $fillable = ['name', 'cost', 'status'];

    public function status()
    {
        return $this->belongsTo(Status::class, 'status');
    }

    public function places()
    {
        return $this->belongsToMany(Place::class, 'places_tolls', 'id_toll', 'id_place')
                    ->withTimestamps();
    }
}
