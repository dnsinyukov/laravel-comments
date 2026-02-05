<?php

// src/Facades/LightComments.php

namespace Coderden\Comments\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array getCommentsForPage(string $commentableType, int $commentableId, int $page = 1, int $perPage = 20, string $sort = 'rating_desc')
 * @method static array getCommentThread(int $commentId)
 * @method static array getCommentLikesHistory(int $commentId, int $page = 1, int $perPage = 20)
 * @method static array createComment(array $data)
 * @method static array updateComment(int $commentId, array $data, int $userId)
 * @method static array reportComment(int $commentId, array $data)
 * @method static array createReply(int $parentId, array $data)
 * @method static array getCommentLikes(int $commentId, int $page = 1, int $perPage = 50)
 * @method static array toggleLike(int $commentId, int $userId, string $type)
 * @method static array getUserCommentsStats(int $userId)
 * 
 * @see \Coderden\Comments\Services\LightCommentService
 */
class LightComments extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'light.comments';
    }
}