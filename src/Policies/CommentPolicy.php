<?php

namespace Coderden\Comments\Policies;

use App\Models\User;
use Coderden\Comments\Models\Comment;

class CommentPolicy
{
    /**
     * Determine if the user can view the comment.
     */
    public function view(User $user, Comment $comment): bool
    {
        return $comment->status === 'published' || 
               $user->id === $comment->user_id ||
               $user->hasRole('moderator');
    }
    
    /**
     * Determine if the user can create comments.
     */
    public function create(User $user): bool
    {
        // Проверка лимита комментариев в день
        $dailyLimit = config('comments.limits.daily_comments', 50);
        $todayComments = $user->comments()
            ->whereDate('created_at', today())
            ->count();
        
        return $todayComments < $dailyLimit;
    }
    
    /**
     * Determine if the user can update the comment.
     */
    public function update(User $user, Comment $comment): bool
    {
        return $comment->canEdit($user);
    }
    
    /**
     * Determine if the user can delete the comment.
     */
    public function delete(User $user, Comment $comment): bool
    {
        return $comment->canDelete($user);
    }
    
    /**
     * Determine if the user can report the comment.
     */
    public function report(User $user, Comment $comment): bool
    {
        if ($user->id === $comment->user_id) {
            return false; // Нельзя жаловаться на свой комментарий
        }
        
        // Проверка лимита жалоб в день
        $dailyLimit = config('comments.limits.abuse_reports_per_day', 5);
        $todayReports = $user->commentAbuseReports()
            ->whereDate('created_at', today())
            ->count();
        
        return $todayReports < $dailyLimit;
    }
    
    /**
     * Determine if the user can like/dislike the comment.
     */
    public function like(User $user, Comment $comment): bool
    {
        return $user->id !== $comment->user_id; // Нельзя голосовать за свой комментарий
    }
    
    /**
     * Determine if the user can moderate comments.
     */
    public function moderate(User $user): bool
    {
        return $user->hasRole(['admin', 'moderator']);
    }
}