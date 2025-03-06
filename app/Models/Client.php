<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'id_client_type',
        'id_client_class',
        'name',
        'lastname',
        'mail',
        'phone',
        'address',
        'status',
        'cuit',
        'bonus_percentage'
    ];

    protected $hidden = [
        'id_client_type',
        'id_client_class',
    ];

    // Relación con otros modelos (ejemplo)
    public function clientType()
    {
        return $this->belongsTo(ClientsType::class, 'id_client_type');
    }

    public function clientClass()
    {
        return $this->belongsTo(ClientsClasses::class, 'id_client_class');
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status');
    }

    public function contacts()
    {
        return $this->hasMany(ClientsContact::class, 'id_client');
    }

}

