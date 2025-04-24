<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetPdfText extends Model
{
    protected $fillable = [
        'payment_method',
        'security_deposit',
        'validity_days',
        'warnings',
        'no_price_products'
    ];

    public $timestamps = true;
}
