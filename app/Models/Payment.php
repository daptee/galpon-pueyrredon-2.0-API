<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $table = 'payments';

    protected $fillable = [
        'id_budget',
        'id_user',
        'payment_datetime',
        'id_payment_type',
        'id_payment_method',
        'amount',
        'observations',
        'id_payment_status',
    ];

    // === Relaciones ===

    public function budget()
    {
        return $this->belongsTo(Budget::class, 'id_budget');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function paymentType()
    {
        return $this->belongsTo(PaymentType::class, 'id_payment_type');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'id_payment_method');
    }

    public function paymentStatus()
    {
        return $this->belongsTo(PaymentStatus::class, 'id_payment_status');
    }

    public function statusHistory()
    {
        return $this->hasMany(PaymentStatusHistory::class, 'id_payment');
    }
}
