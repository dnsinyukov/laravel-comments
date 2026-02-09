<?php

namespace Coderden\Comments\Events;

use Illuminate\Foundation\Events\Dispatchable;

class CommentLiked
{
    use Dispatchable;

    public $commentId;
    public $userId;
    public $type;
    public $action;

    public function __construct(int $commentId, int $userId, string $action)
    {
        $this->commentId = $commentId;
        $this->userId = $userId;
        $this->action = $action;
    }
}