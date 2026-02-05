<?php

namespace Coderden\Comments\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Coderden\Comments\Models\Comment;
use Coderden\Comments\Models\CommentAbuseReport;

class CommentReported
{
    use Dispatchable, SerializesModels;

    public $comment;
    public $report;

    public function __construct(Comment $comment, CommentAbuseReport $report)
    {
        $this->comment = $comment;
        $this->report = $report;
    }
}