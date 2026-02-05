<?php

namespace Coderden\Comments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentAttachment extends Model
{
    protected $table = 'comment_attachments';
    
    protected $fillable = [
        'comment_id',
        'type',
        'path',
        'original_name',
        'mime_type',
        'size',
        'width',
        'height',
        'duration',
        'order',
        'status',
    ];
    
    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'duration' => 'integer',
        'order' => 'integer',
    ];
    
    public function comment(): BelongsTo
    {
        $commentModel = config('comments.models.comment');
        return $this->belongsTo($commentModel);
    }
    
    public function getUrlAttribute(): string
    {
        $disk = config('comments.attachments.disk', 'public');
        return \Storage::disk($disk)->url($this->path);
    }
}