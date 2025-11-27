<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'payment_status_history';

    protected $fillable = [
        'id_payment',
        'id_user',
        'id_payment_status',
        'datetime',
        'observations',
    ];

    // === Relaciones ===

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'id_payment');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function paymentStatus()
    {
        return $this->belongsTo(PaymentStatus::class, 'id_payment_status');
    }
}
