<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetStatus extends Model
{
    protected $fillable = ['name'];

    protected $table = 'budget_status';

    public function budgets()
    {
        return $this->hasMany(Budget::class);
    }
}
