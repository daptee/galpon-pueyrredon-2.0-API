<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Place extends Model
{
    use HasFactory;

    protected $table = 'places';

    protected $fillable = [
        'id_place_type', 'name', 'id_province', 'id_locality', 'id_place_collection_type',
        'distance', 'travel_time', 'address', 'phone', 'complexity_factor', 'observations', 'status'
    ];

    protected $hidden = [
        'id_place_type',
        'id_province',
        'id_locality',
        'id_place_collection_type'
    ];

    public function placeType()
    {
        return $this->belongsTo(PlaceType::class, 'id_place_type');
    }

    public function province()
    {
        return $this->belongsTo(Province::class, 'id_province');
    }

    public function locality()
    {
        return $this->belongsTo(Locality::class, 'id_locality');
    }

    public function placeCollectionType()
    {
        return $this->belongsTo(PlaceCollectionType::class, 'id_place_collection_type');
    }

    public function tolls()
    {
        return $this->belongsToMany(Toll::class, 'places_tolls', 'id_place', 'id_toll')
                    ->withTimestamps(); // Para incluir created_at y updated_at
    }
}
