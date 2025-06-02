<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    protected $fillable = [
        'id_budget',
        'id_client',
        'client_mail',
        'client_phone',
        'id_place',
        'id_transportation',
        'date_event',
        'time_event',
        'days',
        'quoted_days',
        'total_price_products',
        'client_bonification',
        'client_bonification_edited',
        'total_bonification',
        'transportation_cost',
        'transportation_cost_edited',
        'subtotal',
        'iva',
        'total',
        'version_number',
        'id_budget_status',
        'products_has_prices',
        'observations'
    ];

    public $timestamps = true;

    public function budgetStatus()
    {
        return $this->belongsTo(BudgetStatus::class, 'id_budget_status');
    }

    public function audith()
    {
        return $this->hasMany(BudgetAudith::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'id_client');
    }

    public function place()
    {
        return $this->belongsTo(Place::class, 'id_place');
    }

    public function transportation()
    {
        return $this->belongsTo(Transportation::class, 'id_transportation');
    }

    public function budgetProducts()
    {
        return $this->hasMany(BudgetProducts::class, 'id_budget');
    }
}
