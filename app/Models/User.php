<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $fillable = [
        'user',
        'password',
        'email',
        'id_user_type',
        'name',
        'lastname',
        'phone',
        'is_internal',
        'id_client',
        'permissions',
        'theme',
        'status',
        'last_activity',
    ];

    // password y remember_token nunca se serializan: el usuario viaja anidado en
    // otras respuestas (pagos, presupuestos, etc.) donde no se llama a makeHidden.
    protected $hidden = [
        'password',
        'remember_token',
        'id_user_type',
        'id_client'
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_internal' => 'boolean',
        'last_activity' => 'datetime',
        'two_factor_enabled' => 'boolean',
    ];

    // Métodos requeridos por la interfaz JWTSubject
    public function getJWTIdentifier()
    {
        return $this->getKey(); // Retorna la clave primaria del usuario (id por defecto)
    }

    public function getJWTCustomClaims()
    {
        return []; // Puedes agregar cualquier información personalizada al token
    }

    public function userType()
    {
        return $this->belongsTo(UserType::class, 'id_user_type');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'id_client');
    }

    public function theme()
    {
        return $this->belongsTo(Theme::class, 'theme');
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status');
    }
}
