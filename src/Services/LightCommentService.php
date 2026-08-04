<?php

namespace Coderden\Comments\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class LightCommentService
{
    /**
     * Получить список комментариев для страницы с пагинацией
     */
    public function getCommentsForPage(
        string $commentableType,
        int $commentableId,
        int $page = 1,
        int $perPage = 20,
        string $sort = 'rating_desc'
    ): array {
        $offset = ($page - 1) * $perPage;
        
        // Определяем сортировку
        $orderBy = match($sort) {
            'newest' => 'comments.created_at DESC',
            'oldest' => 'comments.created_at ASC',
            'rating_desc' => 'comments.rating DESC, comments.created_at DESC',
            'rating_asc' => 'comments.rating ASC, comments.created_at ASC',
            default => 'comments.rating DESC, comments.created_at DESC',
        };
        
        // Получаем корневые комментарии
        $comments = DB::select("
            SELECT 
                c.id,
                c.content,
                c.rating,
                c.likes_count,
                c.dislikes_count,
                c.replies_count,
                c.status,
                c.created_at,
                c.user_id,
                u.name as user_name,
                u.avatar as user_avatar,
                (
                    SELECT COUNT(*)
                    FROM comments as rc
                    WHERE rc.parent_id = c.id
                    AND rc.status = 'published'
                ) as total_replies
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.commentable_type = ?
            AND c.commentable_id = ?
            AND c.parent_id IS NULL
            AND c.status = 'published'
            ORDER BY $orderBy
            LIMIT ? OFFSET ?
        ", [$commentableType, $commentableId, $perPage, $offset]);
        
        // Получаем общее количество для пагинации
        $total = DB::selectOne("
            SELECT COUNT(*) as total
            FROM comments
            WHERE commentable_type = ?
            AND commentable_id = ?
            AND parent_id IS NULL
            AND status = 'published'
        ", [$commentableType, $commentableId])->total;
        
        // Форматируем результат
        $formattedComments = array_map(function($comment) {
            $comment->created_at = Carbon::parse($comment->created_at);
            $comment->user = [
                'id' => $comment->user_id,
                'name' => $comment->user_name,
                'avatar' => $comment->user_avatar,
            ];
            unset($comment->user_id, $comment->user_name, $comment->user_avatar);
            return $comment;
        }, $comments);
        
        // Создаем пагинатор вручную
        // $paginator = new LengthAwarePaginator(
        //     $formattedComments,
        //     $total,
        //     $perPage,
        //     $page,
        //     ['path' => request()->url(), 'query' => request()->query()]
        // );
        
        return [
            'data' => $formattedComments,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total),
            ]
        ];
    }
    
    /**
     * Получить ветку комментариев (комментарий и все ответы)
     */
    public function getCommentThread(int $commentId): array
    {
        // Получаем основной комментарий
        $mainComment = DB::selectOne("
            SELECT 
                c.id,
                c.content,
                c.rating,
                c.likes_count,
                c.dislikes_count,
                c.replies_count,
                c.status,
                c.created_at,
                c.user_id,
                u.name as user_name,
                u.avatar as user_avatar
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.id = ?
            AND c.status = 'published'
        ", [$commentId]);
        
        if (!$mainComment) {
            return [];
        }
        
        // Получаем все ответы (все уровни вложенности)
        $replies = DB::select("
            WITH RECURSIVE comment_tree AS (
                -- Начальный уровень: прямые ответы
                SELECT 
                    c.id,
                    c.content,
                    c.rating,
                    c.likes_count,
                    c.dislikes_count,
                    c.status,
                    c.created_at,
                    c.user_id,
                    u.name as user_name,
                    u.avatar as user_avatar,
                    c.parent_id,
                    1 as level
                FROM comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.parent_id = ?
                AND c.status = 'published'
                
                UNION ALL
                
                -- Рекурсивно получаем следующие уровни
                SELECT 
                    c.id,
                    c.content,
                    c.rating,
                    c.likes_count,
                    c.dislikes_count,
                    c.status,
                    c.created_at,
                    c.user_id,
                    u.name as user_name,
                    u.avatar as user_avatar,
                    c.parent_id,
                    ct.level + 1
                FROM comments c
                JOIN comment_tree ct ON c.parent_id = ct.id
                JOIN users u ON c.user_id = u.id
                WHERE c.status = 'published'
            )
            SELECT * FROM comment_tree
            ORDER BY level, created_at
        ", [$commentId]);
        
        // Форматируем основной комментарий
        $mainComment->created_at = Carbon::parse($mainComment->created_at);
        $mainComment->user = [
            'id' => $mainComment->user_id,
            'name' => $mainComment->user_name,
            'avatar' => $mainComment->user_avatar,
        ];
        unset($mainComment->user_id, $mainComment->user_name, $mainComment->user_avatar);
        
        // Форматируем ответы
        $formattedReplies = array_map(function($reply) {
            $reply->created_at = Carbon::parse($reply->created_at);
            $reply->user = [
                'id' => $reply->user_id,
                'name' => $reply->user_name,
                'avatar' => $reply->user_avatar,
            ];
            unset($reply->user_id, $reply->user_name, $reply->user_avatar);
            return $reply;
        }, $replies);
        
        return [
            'comment' => $mainComment,
            'replies' => $formattedReplies,
            'replies_count' => count($formattedReplies),
        ];
    }
    
    /**
     * История лайков комментария
     */
    public function getCommentLikesHistory(int $commentId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        
        // Получаем историю лайков
        $likes = DB::select("
            SELECT 
                cl.id,
                cl.type,
                cl.created_at,
                cl.user_id,
                u.name as user_name,
                u.avatar as user_avatar
            FROM comment_likes cl
            JOIN users u ON cl.user_id = u.id
            WHERE cl.comment_id = ?
            ORDER BY cl.created_at DESC
            LIMIT ? OFFSET ?
        ", [$commentId, $perPage, $offset]);
        
        // Получаем общее количество
        $total = DB::selectOne("
            SELECT COUNT(*) as total
            FROM comment_likes
            WHERE comment_id = ?
        ", [$commentId])->total;
        
        // Форматируем результат
        $formattedLikes = array_map(function($like) {
            $like->created_at = Carbon::parse($like->created_at);
            $like->user = [
                'id' => $like->user_id,
                'name' => $like->user_name,
                'avatar' => $like->user_avatar,
            ];
            unset($like->user_id, $like->user_name, $like->user_avatar);
            return $like;
        }, $likes);
        
        // Статистика по типам
        $stats = DB::selectOne("
            SELECT 
                SUM(CASE WHEN type = 'like' THEN 1 ELSE 0 END) as likes,
                SUM(CASE WHEN type = 'dislike' THEN 1 ELSE 0 END) as dislikes
            FROM comment_likes
            WHERE comment_id = ?
        ", [$commentId]);
        
        return [
            'data' => $formattedLikes,
            'stats' => $stats,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage),
            ]
        ];
    }
    
    /**
     * Создать комментарий
     */
    public function createComment(array $data): array
    {
        DB::beginTransaction();
        
        try {
            // Проверяем лимит комментариев в день
            $userId = $data['user_id'];
            $today = now()->toDateString();
            
            $dailyCount = DB::selectOne("
                SELECT COUNT(*) as count
                FROM comments
                WHERE user_id = ?
                AND DATE(created_at) = ?
            ", [$userId, $today])->count;
            
            $dailyLimit = config('comments.limits.daily_comments', 50);
            if ($dailyCount >= $dailyLimit) {
                throw new \Exception("Daily comment limit reached ({$dailyLimit})");
            }
            
            // Вставляем комментарий
            $commentId = DB::table('comments')->insertGetId([
                'commentable_type' => $data['commentable_type'],
                'commentable_id' => $data['commentable_id'],
                'user_id' => $userId,
                'parent_id' => $data['parent_id'] ?? null,
                'content' => $data['content'],
                'rating' => 0,
                'likes_count' => 0,
                'dislikes_count' => 0,
                'replies_count' => 0,
                'abuse_reports_count' => 0,
                'status' => 'published',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Если это ответ, увеличиваем счетчик у родителя
            if (!empty($data['parent_id'])) {
                DB::update("
                    UPDATE comments 
                    SET replies_count = replies_count + 1,
                        updated_at = ?
                    WHERE id = ?
                ", [now(), $data['parent_id']]);
            }
            
            // Получаем созданный комментарий
            $comment = DB::selectOne("
                SELECT 
                    c.*,
                    u.name as user_name,
                    u.avatar as user_avatar
                FROM comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.id = ?
            ", [$commentId]);
            
            DB::commit();
            
            // Форматируем результат
            $comment->created_at = Carbon::parse($comment->created_at);
            $comment->user = [
                'id' => $comment->user_id,
                'name' => $comment->user_name,
                'avatar' => $comment->user_avatar,
            ];
            
            return [
                'success' => true,
                'comment' => $comment,
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Обновить комментарий
     */
    public function updateComment(int $commentId, array $data, int $userId): array
    {
        // Проверяем права
        $comment = DB::selectOne("
            SELECT user_id, created_at
            FROM comments
            WHERE id = ?
            AND status != 'deleted'
        ", [$commentId]);
        
        if (!$comment) {
            return ['success' => false, 'error' => 'Comment not found'];
        }
        
        // Проверяем, можно ли редактировать
        $editTime = config('comments.limits.edit_time', 15);
        $canEdit = $comment->user_id == $userId && 
                   Carbon::parse($comment->created_at)->addMinutes($editTime)->isFuture();
        
        if (!$canEdit) {
            return ['success' => false, 'error' => 'Cannot edit this comment'];
        }
        
        // Обновляем комментарий
        DB::update("
            UPDATE comments
            SET content = ?,
                updated_at = ?
            WHERE id = ?
        ", [$data['content'], now(), $commentId]);
        
        // Получаем обновленный комментарий
        $updated = DB::selectOne("
            SELECT 
                c.*,
                u.name as user_name,
                u.avatar as user_avatar
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.id = ?
        ", [$commentId]);
        
        $updated->created_at = Carbon::parse($updated->created_at);
        $updated->updated_at = Carbon::parse($updated->updated_at);
        $updated->user = [
            'id' => $updated->user_id,
            'name' => $updated->user_name,
            'avatar' => $updated->user_avatar,
        ];
        
        return [
            'success' => true,
            'comment' => $updated,
        ];
    }
    
    /**
     * Пожаловаться на комментарий
     */
    public function reportComment(int $commentId, array $data): array
    {
        $userId = $data['user_id'];
        
        // Проверяем, не жаловался ли уже пользователь
        $existingReport = DB::selectOne("
            SELECT id
            FROM comment_abuse_reports
            WHERE comment_id = ?
            AND user_id = ?
            AND status = 'pending'
        ", [$commentId, $userId]);
        
        if ($existingReport) {
            return ['success' => false, 'error' => 'You have already reported this comment'];
        }
        
        // Проверяем лимит жалоб в день
        $today = now()->toDateString();
        $dailyReports = DB::selectOne("
            SELECT COUNT(*) as count
            FROM comment_abuse_reports
            WHERE user_id = ?
            AND DATE(created_at) = ?
        ", [$userId, $today])->count;
        
        $dailyLimit = config('comments.limits.abuse_reports_per_day', 5);
        if ($dailyReports >= $dailyLimit) {
            return ['success' => false, 'error' => 'Daily report limit reached'];
        }
        
        DB::beginTransaction();
        
        try {
            // Создаем жалобу
            $reportId = DB::table('comment_abuse_reports')->insertGetId([
                'comment_id' => $commentId,
                'user_id' => $userId,
                'reason' => $data['reason'],
                'description' => $data['description'] ?? null,
                'status' => 'pending',
                'ip_address' => request()->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Увеличиваем счетчик жалоб у комментария
            DB::update("
                UPDATE comments
                SET abuse_reports_count = abuse_reports_count + 1,
                    updated_at = ?
                WHERE id = ?
            ", [now(), $commentId]);
            
            // Проверяем порог для автоматического скрытия
            $threshold = config('comments.limits.abuse_threshold', 5);
            $reportsCount = DB::selectOne("
                SELECT abuse_reports_count
                FROM comments
                WHERE id = ?
            ", [$commentId])->abuse_reports_count;
            
            if ($reportsCount >= $threshold) {
                DB::update("
                    UPDATE comments
                    SET status = 'hidden',
                        updated_at = ?
                    WHERE id = ?
                ", [now(), $commentId]);
            }
            
            DB::commit();
            
            // Получаем созданную жалобу
            $report = DB::selectOne("
                SELECT *
                FROM comment_abuse_reports
                WHERE id = ?
            ", [$reportId]);
            
            return [
                'success' => true,
                'report' => $report,
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Создать ответ на комментарий
     */
    public function createReply(int $parentId, array $data): array
    {
        // Проверяем существование родительского комментария
        $parent = DB::selectOne("
            SELECT id, commentable_type, commentable_id
            FROM comments
            WHERE id = ?
            AND status = 'published'
        ", [$parentId]);
        
        if (!$parent) {
            return ['success' => false, 'error' => 'Parent comment not found'];
        }
        
        // Проверяем глубину вложенности
        $depth = $this->getCommentDepth($parentId);
        $maxDepth = config('comments.limits.reply_depth', 5);
        
        if ($depth >= $maxDepth) {
            return ['success' => false, 'error' => 'Maximum reply depth reached'];
        }
        
        // Создаем ответ
        $replyData = array_merge($data, [
            'commentable_type' => $parent->commentable_type,
            'commentable_id' => $parent->commentable_id,
            'parent_id' => $parentId,
        ]);
        
        return $this->createComment($replyData);
    }
    
    /**
     * Получить список лайков комментария
     */
    public function getCommentLikes(int $commentId, int $page = 1, int $perPage = 50): array
    {
        $offset = ($page - 1) * $perPage;
        
        // Получаем лайки с информацией о пользователях
        $likes = DB::select("
            SELECT 
                cl.id,
                cl.type,
                cl.created_at,
                cl.user_id,
                u.name as user_name,
                u.avatar as user_avatar
            FROM comment_likes cl
            JOIN users u ON cl.user_id = u.id
            WHERE cl.comment_id = ?
            ORDER BY cl.created_at DESC
            LIMIT ? OFFSET ?
        ", [$commentId, $perPage, $offset]);
        
        // Получаем общее количество
        $total = DB::selectOne("
            SELECT COUNT(*) as total
            FROM comment_likes
            WHERE comment_id = ?
        ", [$commentId])->total;
        
        // Группируем по типу
        $byType = DB::select("
            SELECT 
                type,
                COUNT(*) as count
            FROM comment_likes
            WHERE comment_id = ?
            GROUP BY type
        ", [$commentId]);
        
        // Форматируем результат
        $formattedLikes = array_map(function($like) {
            $like->created_at = Carbon::parse($like->created_at);
            $like->user = [
                'id' => $like->user_id,
                'name' => $like->user_name,
                'avatar' => $like->user_avatar,
            ];
            unset($like->user_id, $like->user_name, $like->user_avatar);
            return $like;
        }, $likes);
        
        // Форматируем статистику по типам
        $typeStats = [];
        foreach ($byType as $stat) {
            $typeStats[$stat->type] = $stat->count;
        }
        
        return [
            'data' => $formattedLikes,
            'stats' => $typeStats,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage),
            ]
        ];
    }
    
    /**
     * Поставить/убрать лайк/дизлайк
     */
    public function toggleLike(int $commentId, int $userId, string $type): array
    {
        DB::beginTransaction();
        
        try {
            // Проверяем существующую реакцию
            $existing = DB::selectOne("
                SELECT id, type
                FROM comment_likes
                WHERE comment_id = ?
                AND user_id = ?
            ", [$commentId, $userId]);
            
            $action = 'none';
            
            if ($existing) {
                if ($existing->type === $type) {
                    // Удаляем реакцию
                    DB::delete("
                        DELETE FROM comment_likes
                        WHERE id = ?
                    ", [$existing->id]);
                    
                    $action = 'removed';
                    
                    // Обновляем счетчики комментария
                    if ($type === 'like') {
                        DB::update("
                            UPDATE comments
                            SET likes_count = likes_count - 1,
                                rating = rating - 1,
                                updated_at = ?
                            WHERE id = ?
                        ", [now(), $commentId]);
                    } else {
                        DB::update("
                            UPDATE comments
                            SET dislikes_count = dislikes_count - 1,
                                rating = rating + 1,
                                updated_at = ?
                            WHERE id = ?
                        ", [now(), $commentId]);
                    }
                } else {
                    // Меняем тип реакции
                    DB::update("
                        UPDATE comment_likes
                        SET type = ?,
                            created_at = ?
                        WHERE id = ?
                    ", [$type, now(), $existing->id]);
                    
                    $action = 'changed';
                    
                    // Обновляем счетчики комментария
                    if ($existing->type === 'like' && $type === 'dislike') {
                        // Было like, стало dislike
                        DB::update("
                            UPDATE comments
                            SET likes_count = likes_count - 1,
                                dislikes_count = dislikes_count + 1,
                                rating = rating - 2,
                                updated_at = ?
                            WHERE id = ?
                        ", [now(), $commentId]);
                    } else {
                        // Было dislike, стало like
                        DB::update("
                            UPDATE comments
                            SET likes_count = likes_count + 1,
                                dislikes_count = dislikes_count - 1,
                                rating = rating + 2,
                                updated_at = ?
                            WHERE id = ?
                        ", [now(), $commentId]);
                    }
                }
            } else {
                // Создаем новую реакцию
                DB::insert("
                    INSERT INTO comment_likes (comment_id, user_id, type, ip_address, created_at)
                    VALUES (?, ?, ?, ?, ?)
                ", [$commentId, $userId, $type, request()->ip(), now()]);
                
                $action = 'added';
                
                // Обновляем счетчики комментария
                if ($type === 'like') {
                    DB::update("
                        UPDATE comments
                        SET likes_count = likes_count + 1,
                            rating = rating + 1,
                            updated_at = ?
                        WHERE id = ?
                    ", [now(), $commentId]);
                } else {
                    DB::update("
                        UPDATE comments
                        SET dislikes_count = dislikes_count + 1,
                            rating = rating - 1,
                            updated_at = ?
                        WHERE id = ?
                    ", [now(), $commentId]);
                }
            }
            
            // Получаем обновленные счетчики
            $comment = DB::selectOne("
                SELECT likes_count, dislikes_count, rating
                FROM comments
                WHERE id = ?
            ", [$commentId]);
            
            // Получаем текущую реакцию пользователя
            $userReaction = DB::selectOne("
                SELECT type
                FROM comment_likes
                WHERE comment_id = ?
                AND user_id = ?
            ", [$commentId, $userId]);
            
            DB::commit();
            
            return [
                'success' => true,
                'action' => $action,
                'likes_count' => $comment->likes_count,
                'dislikes_count' => $comment->dislikes_count,
                'rating' => $comment->rating,
                'user_reaction' => $userReaction ? $userReaction->type : null,
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Получить глубину вложенности комментария
     */
    private function getCommentDepth(int $commentId, int $currentDepth = 0): int
    {
        $parent = DB::selectOne("
            SELECT parent_id
            FROM comments
            WHERE id = ?
        ", [$commentId]);
        
        if (!$parent || !$parent->parent_id) {
            return $currentDepth;
        }
        
        return $this->getCommentDepth($parent->parent_id, $currentDepth + 1);
    }
    
    /**
     * Получить статистику комментариев пользователя
     */
    public function getUserCommentsStats(int $userId): array
    {
        $stats = DB::selectOne("
            SELECT 
                COUNT(*) as total_comments,
                SUM(likes_count) as total_likes,
                SUM(dislikes_count) as total_dislikes,
                SUM(replies_count) as total_replies
            FROM comments
            WHERE user_id = ?
            AND status = 'published'
        ", [$userId]);
        
        $today = DB::selectOne("
            SELECT COUNT(*) as today_comments
            FROM comments
            WHERE user_id = ?
            AND DATE(created_at) = CURDATE()
            AND status = 'published'
        ", [$userId])->today_comments;
        
        return [
            'total_comments' => (int) $stats->total_comments,
            'total_likes' => (int) $stats->total_likes,
            'total_dislikes' => (int) $stats->total_dislikes,
            'total_replies' => (int) $stats->total_replies,
            'today_comments' => (int) $today,
            'daily_limit' => config('comments.limits.daily_comments', 50),
        ];
    }
}