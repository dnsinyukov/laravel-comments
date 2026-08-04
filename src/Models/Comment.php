<?php

namespace Coderden\Comments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class Comment extends Model
{
    use SoftDeletes;
    
    protected $table = 'comments';
    
    protected $fillable = [
        'commentable_type',
        'commentable_id',
        'user_id',
        'parent_id',
        'content',
        'rating',
        'status',
        'ip_address',
        'user_agent',
        'meta',
    ];
    
    protected $casts = [
        'rating' => 'integer',
        'likes_count' => 'integer',
        'dislikes_count' => 'integer',
        'replies_count' => 'integer',
        'abuse_reports_count' => 'integer',
        'meta' => AsArrayObject::class,
    ];
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($comment) {
            $comment->ip_address = request()->ip();
            $comment->user_agent = request()->userAgent();
            
            if (config('comments.moderation.require_approval')) {
                $comment->status = 'pending';
            }
        });
        
        static::deleting(function ($comment) {
            if ($comment->parent_id) {
                $comment->parent->decrement('replies_count');
            }
        });
    }
    
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
    
    public function user(): BelongsTo
    {
        $userModel = config('comments.models.user', '\App\Models\User');
        return $this->belongsTo($userModel);
    }
    
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }
    
    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }
    
    public function likes(): HasMany
    {
        $likeModel = config('comments.models.comment_like');
        return $this->hasMany($likeModel);
    }
    
    public function abuseReports(): HasMany
    {
        $reportModel = config('comments.models.abuse_report');
        return $this->hasMany($reportModel);
    }
    
    public function attachments(): HasMany
    {
        $attachmentModel = config('comments.models.attachment');
        return $this->hasMany($attachmentModel);
    }
    
    public function isLikedBy($user): bool
    {
        if (!$user) return false;
        
        return $this->likes()
            ->where('user_id', $user->id)
            ->where('type', 'like')
            ->exists();
    }
    
    public function isDislikedBy($user): bool
    {
        if (!$user) return false;
        
        return $this->likes()
            ->where('user_id', $user->id)
            ->where('type', 'dislike')
            ->exists();
    }
    
    public function isReportedBy($user): bool
    {
        if (!$user) return false;
        
        return $this->abuseReports()
            ->where('user_id', $user->id)
            ->exists();
    }
    
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
    
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
    
    public function scopeHidden($query)
    {
        return $query->where('status', 'hidden');
    }
    
    public function getDepthAttribute(): int
    {
        if (!$this->parent_id) return 0;
        
        $depth = 1;
        $parent = $this->parent;
        
        while ($parent && $parent->parent_id) {
            $depth++;
            $parent = $parent->parent;
            
            if ($depth >= config('comments.limits.reply_depth', 5)) {
                break;
            }
        }
        
        return $depth;
    }
    
    public function canEdit($user): bool
    {
        if (!$user) return false;
        
        if ($user->id === $this->user_id) {
            $editTime = config('comments.limits.edit_time', 15);
            return $this->created_at->addMinutes($editTime)->isFuture();
        }
        
        return $user->hasRole(['admin', 'moderator']);
    }
    
    public function canDelete($user): bool
    {
        if (!$user) return false;
        
        if ($user->id === $this->user_id) {
            $deleteTime = config('comments.limits.delete_time', 30);
            return $this->created_at->addMinutes($deleteTime)->isFuture();
        }
        
        return $user->hasRole(['admin', 'moderator']);
    }
}