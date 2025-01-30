<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transportation extends Model
{
    use HasFactory;

    protected $table = 'transportations';

    protected $fillable = [
        'name',
        'load_volume_up',
        'schedule_cost',
        'cost_km',
        'charge_discharge_time',
        'minimum_quantity',
        'pawn_quantity',
        'status'
    ];

    public function status()
    {
        return $this->belongsTo(Status::class, 'status');
    }
}
