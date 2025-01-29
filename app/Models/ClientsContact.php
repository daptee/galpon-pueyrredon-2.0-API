<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientsContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_client',
        'name',
        'lastname',
        'mail',
        'phone',
    ];
}
