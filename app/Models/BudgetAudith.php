<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetAudith extends Model
{
    protected $fillable = [
        'id_budget',
        'action',
        'new_budget_status',
        'observations',
        'user',
        'date',
        'time'
    ];

    public $timestamps = true;

    protected $table = 'budgets_audith';

    public function budget()
    {
        return $this->belongsTo(Budget::class, 'id_budget');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user');
    }

    public function parent()
    {
        return $this->belongsTo(Budget::class, 'id_budget');
    }

    public function children()
    {
        return $this->hasMany(Budget::class, 'id_budget');
    }

}
