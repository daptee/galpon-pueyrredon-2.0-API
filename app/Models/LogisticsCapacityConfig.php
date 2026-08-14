<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsCapacityConfig extends Model
{
    protected $table = 'logistics_capacity_configs';

    protected $fillable = [
        'max_daily_events',
        'max_daily_volume',
        'max_successive_events',
        'max_successive_volume',
        'high_demand_threshold',
        'restricted_threshold',
    ];

    public $timestamps = true;
}
