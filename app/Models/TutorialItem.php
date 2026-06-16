<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutorialItem extends Model
{
    protected $table = 'tutorial_items';

    protected $fillable = [
        'id_tutorial_module',
        'id_tutorial_subtopic',
        'title',
        'content',
        'is_published',
        'order',
    ];

    protected $casts = [
        'id_tutorial_module'   => 'integer',
        'id_tutorial_subtopic' => 'integer',
        'is_published'         => 'boolean',
        'order'                => 'integer',
    ];

    public function module()
    {
        return $this->belongsTo(TutorialModule::class, 'id_tutorial_module');
    }

    public function subtopic()
    {
        return $this->belongsTo(TutorialSubtopic::class, 'id_tutorial_subtopic');
    }

    public function attachments()
    {
        return $this->hasMany(TutorialAttachment::class, 'id_tutorial_item')->orderBy('order');
    }
}
