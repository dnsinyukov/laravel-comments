<?php

namespace Coderden\Comments\Events;

use Illuminate\Foundation\Events\Dispatchable;

class CommentReported
{
    use Dispatchable;

    public int $commentId;
    public int $reportId;

    public function __construct(int $commentId, int $reportId)
    {
        $this->commentId = $commentId;
        $this->reportId = $reportId;
    }
}