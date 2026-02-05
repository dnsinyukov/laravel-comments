<?php

namespace Coderden\Comments\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Coderden\Comments\Models\Comment;
use Coderden\Comments\Services\CommentService;
use App\Http\Controllers\Controller;

class CommentController extends Controller
{
    protected CommentService $service;
    
    public function __construct(CommentService $service)
    {
        $this->service = $service;
        
        $this->middleware('auth:sanctum')->except(['index', 'replies']);
    }
    
    public function index(Request $request)
    {
        $validated = $request->validate([
            'commentable_type' => ['required', 'string'],
            'commentable_id' => 'required|integer',
            'per_page' => 'integer|min:1|max:100',
            'sort_by' => 'in:rating,created_at,likes_count',
            'sort_order' => 'in:asc,desc',
        ]);
        
        $comments = $this->service->getThread(
            $validated['commentable_type'],
            $validated['commentable_id'],
            $validated
        );
        
        return response()->json([
            'success' => true,
            'data' => $comments,
        ]);
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'commentable_type' => ['required', 'string'],
            'commentable_id' => 'required|integer',
            'content' => 'required|string|min:1|max:5000',
            'parent_id' => 'nullable|integer|exists:comments,id',
        ]);
        
        $comment = $this->service->create(
            array_merge($validated, ['user_id' => $request->user()->id]),
            $request->file('attachments', [])
        );
        
        return response()->json([
            'success' => true,
            'message' => 'Comment created successfully',
            'data' => $comment,
        ], 201);
    }
    
    public function update(Request $request, Comment $comment)
    {
        if (!$comment->canEdit($request->user())) {
            abort(403, 'You cannot edit this comment');
        }
        
        $validated = $request->validate([
            'content' => 'required|string|min:1|max:5000',
        ]);
        
        $updated = $this->service->update(
            $comment,
            $validated,
            $request->file('attachments', [])
        );
        
        return response()->json([
            'success' => true,
            'message' => 'Comment updated',
            'data' => $updated,
        ]);
    }
    
    public function destroy(Request $request, Comment $comment)
    {
        if (!$comment->canDelete($request->user())) {
            abort(403, 'You cannot delete this comment');
        }
        
        $this->service->delete($comment);
        
        return response()->json([
            'success' => true,
            'message' => 'Comment deleted',
        ]);
    }
    
    public function like(Request $request, Comment $comment)
    {
        $validated = $request->validate([
            'type' => 'required|in:like,dislike',
        ]);
        
        $result = $this->service->toggleLike(
            $comment,
            $request->user(),
            $validated['type']
        );
        
        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
    
    public function report(Request $request, Comment $comment)
    {
        $validated = $request->validate([
            'reason' => [
                'required',
                Rule::in(['spam', 'abuse', 'hate_speech', 'adult_content', 'spoiler', 'false_info', 'other'])
            ],
            'description' => 'nullable|string|max:1000',
        ]);
        
        try {
            $report = $this->service->report(
                $comment,
                $request->user(),
                $validated['reason'],
                $validated['description']
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Report submitted',
                'data' => $report,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
    
    public function replies(Request $request, Comment $comment)
    {
        $validated = $request->validate([
            'per_page' => 'integer|min:1|max:100',
        ]);
        
        $replies = $this->service->getReplies($comment, $validated);
        
        return response()->json([
            'success' => true,
            'data' => $replies,
        ]);
    }
}