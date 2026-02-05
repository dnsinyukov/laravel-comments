<?php

namespace Coderden\Comments\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Coderden\Comments\Models\Comment;

class CommentLiked
{
    use Dispatchable, SerializesModels;

    public $comment;
    public $userId;
    public $type;
    public $action;

    public function __construct(Comment $comment, $userId, string $type, string $action)
    {
        $this->comment = $comment;
        $this->userId = $userId;
        $this->type = $type;
        $this->action = $action;
    }
}