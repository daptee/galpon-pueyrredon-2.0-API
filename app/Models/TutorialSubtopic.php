<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutorialSubtopic extends Model
{
    protected $table = 'tutorial_subtopics';

    protected $fillable = [
        'id_tutorial_module',
        'name',
        'description',
        'order',
        'status',
    ];

    protected $casts = [
        'id_tutorial_module' => 'integer',
        'order'              => 'integer',
        'status'             => 'integer',
    ];

    public function module()
    {
        return $this->belongsTo(TutorialModule::class, 'id_tutorial_module');
    }

    public function items()
    {
        return $this->hasMany(TutorialItem::class, 'id_tutorial_subtopic')->orderBy('order');
    }
}
