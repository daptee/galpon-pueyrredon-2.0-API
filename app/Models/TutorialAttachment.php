<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutorialAttachment extends Model
{
    protected $table = 'tutorial_attachments';

    protected $fillable = [
        'id_tutorial_item',
        'file_path',
        'original_name',
        'file_type',
        'mime_type',
        'size',
        'order',
    ];

    protected $casts = [
        'id_tutorial_item' => 'integer',
        'size'             => 'integer',
        'order'            => 'integer',
    ];

    public function item()
    {
        return $this->belongsTo(TutorialItem::class, 'id_tutorial_item');
    }
}
