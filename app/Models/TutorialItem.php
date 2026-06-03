<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutorialItem extends Model
{
    protected $table = 'tutorial_items';

    protected $fillable = [
        'id_tutorial_subtopic',
        'title',
        'content',
        'content_type',
        'cover_image',
        'is_published',
        'order',
    ];

    protected $casts = [
        'id_tutorial_subtopic' => 'integer',
        'content_type'         => 'integer',
        'is_published'         => 'boolean',
        'order'                => 'integer',
    ];

    // content_type labels for reference:
    // 1=tutorial, 2=documento, 3=guia, 4=faq, 5=video

    public function subtopic()
    {
        return $this->belongsTo(TutorialSubtopic::class, 'id_tutorial_subtopic');
    }

    public function attachments()
    {
        return $this->hasMany(TutorialAttachment::class, 'id_tutorial_item')->orderBy('order');
    }
}
