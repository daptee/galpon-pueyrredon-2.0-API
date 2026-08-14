<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedDate extends Model
{
    protected $table = 'blocked_dates';

    protected $fillable = [
        'date',
        'reason',
        'id_user',
    ];

    public $timestamps = true;

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}
