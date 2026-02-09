<?php

// src/Listeners/SendCommentNotification.php

namespace Coderden\Comments\Listeners;

use Coderden\Comments\Events\CommentCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendCommentNotification implements ShouldQueue
{
    public function handle(CommentCreated $event)
    {
        $comment = DB::table('comments')->find($event->commentId);

        if (empty($comment)) {
            return;
        }
        
        // Здесь можно реализовать отправку уведомлений
        // Например, отправка email, webhook, или push-уведомление
        
        // Логирование для отладки
        Log::info('New comment created', [
            'comment_id' => $comment->id,
            'user_id' => $comment->user_id,
            'commentable_type' => $comment->commentable_type,
            'commentable_id' => $comment->commentable_id,
        ]);
    }
}