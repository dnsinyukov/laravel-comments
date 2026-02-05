<?php

namespace Coderden\Comments\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Coderden\Comments\Models\Comment create(array $attributes)
 * @method static \Coderden\Comments\Models\Comment update(\Coderden\Comments\Models\Comment $comment, array $attributes)
 * @method static bool delete(\Coderden\Comments\Models\Comment $comment, bool $force = false)
 * @method static array toggleLike(\Coderden\Comments\Models\Comment $comment, \Illuminate\Foundation\Auth\User $user, string $type = 'like')
 * @method static \Coderden\Comments\Models\CommentAbuseReport report(\Coderden\Comments\Models\Comment $comment, \Illuminate\Foundation\Auth\User $user, string $reason, ?string $description = null)
 * @method static \Illuminate\Pagination\LengthAwarePaginator getThread(string $commentableType, int $commentableId, int $perPage = 20, string $sortBy = 'rating', string $sortOrder = 'desc')
 * @method static \Illuminate\Pagination\LengthAwarePaginator getReplies(\Coderden\Comments\Models\Comment $comment, int $perPage = 10)
 * @method static \Illuminate\Pagination\LengthAwarePaginator search(string $query, int $perPage = 20)
 * @method static \Illuminate\Pagination\LengthAwarePaginator getUserComments(\Illuminate\Foundation\Auth\User $user, int $perPage = 20)
 * @method static array|null getUserReaction(\Coderden\Comments\Models\Comment $comment, \Illuminate\Foundation\Auth\User $user)
 * @method static void saveAttachments(\Coderden\Comments\Models\Comment $comment, array $attachments)
 * @method static void deleteAttachments(\Coderden\Comments\Models\Comment $comment)
 * 
 * @see \Coderden\Comments\Services\CommentService
 */
class Comments extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'comments';
    }
}