<?php

namespace Coderden\Comments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentAbuseReport extends Model
{
    protected $table = 'comment_abuse_reports';
    
    protected $fillable = [
        'comment_id',
        'user_id',
        'reason',
        'description',
        'status',
        'moderator_note',
        'moderator_id',
        'ip_address',
    ];
    
    protected $casts = [
        'reviewed_at' => 'datetime',
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
    
    public function moderator(): BelongsTo
    {
        $userModel = config('comments.models.user');
        return $this->belongsTo($userModel, 'moderator_id');
    }
    
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}