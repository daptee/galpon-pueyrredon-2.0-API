<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Audith extends Model
{
    use HasFactory;

    protected $table = 'audith';

    protected $fillable = ['id_user', 'data', 'ip'];
}
