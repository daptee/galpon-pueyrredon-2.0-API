<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutorialModule extends Model
{
    protected $table = 'tutorial_modules';

    protected $fillable = [
        'name',
        'description',
        'order',
        'status',
    ];

    protected $casts = [
        'order'  => 'integer',
        'status' => 'integer',
    ];

    public function subtopics()
    {
        return $this->hasMany(TutorialSubtopic::class, 'id_tutorial_module')->orderBy('order');
    }
}
