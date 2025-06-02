<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentType extends Model
{
    use HasFactory;

    protected $table = 'payment_types';

    protected $fillable = ['name', 'status'];

    public function status()
    {
        return $this->belongsTo(Status::class, 'status');
    }
}
