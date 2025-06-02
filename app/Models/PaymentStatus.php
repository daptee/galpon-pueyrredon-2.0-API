<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentStatus extends Model
{
    use HasFactory;

    protected $table = 'payment_status';

    protected $fillable = ['name', 'status'];

    public function status()
    {
        return $this->belongsTo(Status::class, 'status');
    }
}
