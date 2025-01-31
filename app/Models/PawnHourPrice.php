<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PawnHourPrice extends Model
{
    use HasFactory;

    protected $table = 'pawn_hour_price';

    protected $fillable = ['price', 'status'];

    public function status()
    {
        return $this->belongsTo(Status::class, 'status');
    }
}
