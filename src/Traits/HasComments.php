<?php

// src/Traits/HasComments.php

namespace Coderden\Comments\Traits;

use Coderden\Comments\Models\Comment;

trait HasComments
{
    public function comments()
    {
        return $this->morphMany(
            config('comments.models.comment', Comment::class),
            'commentable'
        );
    }
    
    public function publishedComments()
    {
        return $this->comments()->published();
    }
    
    public function commentsCount()
    {
        return $this->comments()->published()->count();
    }
    
    public function commentAllowed(): bool
    {
        return true;
    }
    
    public function getCommentUrlAttribute(): ?string
    {
        if (method_exists($this, 'getUrlAttribute')) {
            return $this->url . '#comments';
        }
        
        return null;
    }
}