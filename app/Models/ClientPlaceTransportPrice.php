<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientPlaceTransportPrice extends Model
{
    protected $table = 'client_place_transport_prices';

    protected $fillable = [
        'id_client',
        'id_place',
        'observations',
        'status',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'id_client');
    }

    public function place()
    {
        return $this->belongsTo(Place::class, 'id_place');
    }

    public function items()
    {
        return $this->hasMany(ClientPlaceTransportPriceItem::class, 'id_client_place_transport_price')
            ->orderBy('max_volume', 'asc');
    }
}
