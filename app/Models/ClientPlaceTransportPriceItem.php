<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientPlaceTransportPriceItem extends Model
{
    protected $table = 'client_place_transport_price_items';

    protected $fillable = [
        'id_client_place_transport_price',
        'max_volume',
        'price',
    ];

    public function header()
    {
        return $this->belongsTo(ClientPlaceTransportPrice::class, 'id_client_place_transport_price');
    }
}
