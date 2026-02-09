<?php

namespace Coderden\Comments\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentCreated
{
    use Dispatchable, SerializesModels;

    public int $commentId;

    public function __construct(int $commentId)
    {
        $this->commentId = $commentId;
    }
}