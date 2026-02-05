<?php

namespace Coderden\Comments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentLike extends Model
{
    protected $table = 'comment_likes';
    
    protected $fillable = [
        'comment_id',
        'user_id',
        'type',
        'reaction_type',
        'ip_address',
    ];
    
    public function comment(): BelongsTo
    {
        $commentModel = config('comments.models.comment');
        return $this->belongsTo($commentModel);
    }
    
    public function user(): BelongsTo
    {
        $userModel = config('comments.models.user');
        return $this->belongsTo($userModel);
    }
}