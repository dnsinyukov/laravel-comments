<?php

namespace Coderden\Comments\Listeners;

use Coderden\Comments\Events\CommentReported;
use Illuminate\Support\Facades\Mail;
use Coderden\Comments\Mail\AbuseReportNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessAbuseReport implements ShouldQueue
{
    public function handle(CommentReported $event)
    {
        $report = $event->report;
        $comment = $event->comment;
        
        // Здесь можно реализовать логику обработки жалобы
        // Например, отправка уведомления модераторам
        
        // Логирование для отладки
        \Log::info('New abuse report', [
            'report_id' => $report->id,
            'comment_id' => $report->comment_id,
            'user_id' => $report->user_id,
            'reason' => $report->reason,
        ]);
        
        // Отправка email модератору (пример)
        // $moderatorEmail = config('comments.moderation.email');
        // if ($moderatorEmail) {
        //     Mail::to($moderatorEmail)->send(new AbuseReportNotification($report));
        // }
    }
}