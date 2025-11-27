<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BudgetDeliveryData extends Model
{
    use HasFactory;

    protected $table = 'budget_delivery_data';

    protected $fillable = [
        'id_budget',
        'id_event_type',
        'delivery_options',
        'widthdrawal_options',
        'address',
        'id_locality',
        'event_time',
        'coordination_contact',
        'cellphone_coordination',
        'reception_contact',
        'cellphone_reception',
        'additional_delivery_details',
        'additional_order_details',
        'delivery_datetime',
        'widthdrawal_datetime'
    ];

    public function budget()
    {
        return $this->belongsTo(Budget::class, 'id_budget');
    }

    public function eventType()
    {
        return $this->belongsTo(EventType::class, 'id_event_type');
    }

    public function locality()
    {
        return $this->belongsTo(Locality::class, 'id_locality');
    }

}
