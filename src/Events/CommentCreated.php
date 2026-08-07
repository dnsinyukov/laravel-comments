<?php

namespace Coderden\Comments\Events;

use Illuminate\Foundation\Events\Dispatchable;

class CommentCreated
{
    use Dispatchable;

    public int $commentId;

    public function __construct(int $commentId)
    {
        $this->commentId = $commentId;
    }
}