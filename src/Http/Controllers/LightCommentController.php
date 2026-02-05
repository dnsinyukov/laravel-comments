<?php

namespace Coderden\Comments\Http\Controllers;

use Illuminate\Http\Request;
use Coderden\Comments\Services\LightCommentService;

class LightCommentController
{
    private LightCommentService $service;
    
    public function __construct(LightCommentService $service)
    {
        $this->service = $service;
    }
    
    /**
     * Список комментариев для страницы
     */
    public function index(Request $request)
    {
        $request->validate([
            'commentable_type' => 'required|string',
            'commentable_id' => 'required|integer',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'sort' => 'in:newest,oldest,rating_desc,rating_asc',
        ]);
        
        $result = $this->service->getCommentsForPage(
            $request->commentable_type,
            $request->commentable_id,
            $request->page ?? 1,
            $request->per_page ?? 20,
            $request->sort ?? 'rating_desc'
        );
        
        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'pagination' => $result['pagination'],
        ]);
    }
    
    /**
     * Получить ветку комментариев
     */
    public function thread(int $commentId)
    {
        $result = $this->service->getCommentThread($commentId);
        
        if (empty($result)) {
            return response()->json([
                'success' => false,
                'error' => 'Comment not found',
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
    
    /**
     * История лайков комментария
     */
    public function likesHistory(int $commentId, Request $request)
    {
        $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
        ]);
        
        $result = $this->service->getCommentLikesHistory(
            $commentId,
            $request->page ?? 1,
            $request->per_page ?? 20
        );
        
        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'stats' => $result['stats'],
            'pagination' => $result['pagination'],
        ]);
    }
    
    /**
     * Создать комментарий
     */
    public function store(Request $request)
    {
        $request->validate([
            'commentable_type' => 'required|string',
            'commentable_id' => 'required|integer',
            'content' => 'required|string|min:1|max:5000',
            'parent_id' => 'nullable|integer',
        ]);
        
        $data = array_merge($request->all(), [
            'user_id' => $request->user()->id,
        ]);
        
        $result = $this->service->createComment($data);
        
        if (!$result['success']) {
            return response()->json($result, 400);
        }
        
        return response()->json($result, 201);
    }
    
    /**
     * Обновить комментарий
     */
    public function update(int $commentId, Request $request)
    {
        $request->validate([
            'content' => 'required|string|min:1|max:5000',
        ]);
        
        $result = $this->service->updateComment(
            $commentId,
            $request->all(),
            $request->user()->id
        );
        
        if (!$result['success']) {
            return response()->json($result, 400);
        }
        
        return response()->json($result);
    }
    
    /**
     * Пожаловаться на комментарий
     */
    public function report(int $commentId, Request $request)
    {
        $request->validate([
            'reason' => 'required|string|in:spam,abuse,hate_speech,adult_content,spoiler,false_info,other',
            'description' => 'nullable|string|max:1000',
        ]);
        
        $data = array_merge($request->all(), [
            'user_id' => $request->user()->id,
        ]);
        
        $result = $this->service->reportComment($commentId, $data);
        
        if (!$result['success']) {
            return response()->json($result, 400);
        }
        
        return response()->json($result);
    }
    
    /**
     * Ответить на комментарий
     */
    public function reply(int $commentId, Request $request)
    {
        $request->validate([
            'content' => 'required|string|min:1|max:5000',
        ]);
        
        $data = array_merge($request->all(), [
            'user_id' => $request->user()->id,
        ]);
        
        $result = $this->service->createReply($commentId, $data);
        
        if (!$result['success']) {
            return response()->json($result, 400);
        }
        
        return response()->json($result, 201);
    }
    
    /**
     * Список лайков комментария
     */
    public function likes(int $commentId, Request $request)
    {
        $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
        ]);
        
        $result = $this->service->getCommentLikes(
            $commentId,
            $request->page ?? 1,
            $request->per_page ?? 50
        );
        
        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'stats' => $result['stats'],
            'pagination' => $result['pagination'],
        ]);
    }
    
    /**
     * Поставить/убрать лайк/дизлайк
     */
    public function toggleLike(int $commentId, Request $request)
    {
        $request->validate([
            'type' => 'required|in:like,dislike',
        ]);
        
        $result = $this->service->toggleLike(
            $commentId,
            $request->user()->id,
            $request->type
        );
        
        if (!$result['success']) {
            return response()->json($result, 400);
        }
        
        return response()->json($result);
    }
    
    /**
     * Статистика комментариев пользователя
     */
    public function userStats(Request $request)
    {
        $result = $this->service->getUserCommentsStats($request->user()->id);
        
        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}