<?php

namespace Coderden\Comments\Services;

use Coderden\Comments\Models\Comment;
use Coderden\Comments\Models\CommentLike;
use Coderden\Comments\Models\CommentAbuseReport;
use Coderden\Comments\Models\CommentAttachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CommentService
{
    public function create(array $data, array $attachments = []): Comment
    {
        return DB::transaction(function () use ($data, $attachments) {
            $comment = Comment::create(array_merge($data, [
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]));
            
            if ($comment->parent_id) {
                Comment::where('id', $comment->parent_id)->increment('replies_count');
            }
            
            if (!empty($attachments) && config('comments.attachments.enabled')) {
                $this->saveAttachments($comment, $attachments);
            }
            
            event(new \Coderden\Comments\Events\CommentCreated($comment));
            
            return $comment->load(['user', 'attachments']);
        });
    }
    
    public function update(Comment $comment, array $data, array $attachments = []): Comment
    {
        return DB::transaction(function () use ($comment, $data, $attachments) {
            $comment->update($data);
            
            if (config('comments.attachments.enabled')) {
                $this->updateAttachments($comment, $attachments);
            }
            
            return $comment->fresh(['user', 'attachments']);
        });
    }
    
    public function delete(Comment $comment, bool $force = false): bool
    {
        return DB::transaction(function () use ($comment, $force) {
            if ($comment->parent_id) {
                Comment::where('id', $comment->parent_id)->decrement('replies_count');
            }
            
            $this->deleteAttachments($comment);
            
            if ($force) {
                return $comment->forceDelete();
            }
            
            return $comment->delete();
        });
    }
    
    public function toggleLike(Comment $comment, $user, string $type = 'like'): array
    {
        return DB::transaction(function () use ($comment, $user, $type) {
            $existing = CommentLike::where('comment_id', $comment->id)
                ->where('user_id', $user->id)
                ->first();
            
            $action = 'none';
            
            if ($existing) {
                if ($existing->type === $type) {
                    $existing->delete();
                    $action = 'removed';
                    
                    if ($type === 'like') {
                        $comment->decrement('likes_count');
                        $comment->decrement('rating');
                    } else {
                        $comment->decrement('dislikes_count');
                        $comment->increment('rating');
                    }
                } else {
                    $oldType = $existing->type;
                    $existing->update(['type' => $type]);
                    $action = 'changed';
                    
                    if ($oldType === 'like' && $type === 'dislike') {
                        $comment->decrement('likes_count');
                        $comment->increment('dislikes_count');
                        $comment->decrement('rating', 2);
                    } else {
                        $comment->increment('likes_count');
                        $comment->decrement('dislikes_count');
                        $comment->increment('rating', 2);
                    }
                }
            } else {
                CommentLike::create([
                    'comment_id' => $comment->id,
                    'user_id' => $user->id,
                    'type' => $type,
                    'ip_address' => request()->ip(),
                ]);
                
                $action = 'added';
                
                if ($type === 'like') {
                    $comment->increment('likes_count');
                    $comment->increment('rating');
                } else {
                    $comment->increment('dislikes_count');
                    $comment->decrement('rating');
                }
            }
            
            $comment->refresh();
            
            event(new \Coderden\Comments\Events\CommentLiked($comment, $user, $type, $action));
            
            return [
                'action' => $action,
                'likes_count' => $comment->likes_count,
                'dislikes_count' => $comment->dislikes_count,
                'rating' => $comment->rating,
                'user_reaction' => $this->getUserReaction($comment, $user),
            ];
        });
    }
    
    public function report(Comment $comment, $user, string $reason, ?string $description = null): CommentAbuseReport
    {
        $existing = CommentAbuseReport::where('comment_id', $comment->id)
            ->where('user_id', $user->id)
            ->first();
        
        if ($existing) {
            throw new \Exception('You have already reported this comment');
        }
        
        $report = CommentAbuseReport::create([
            'comment_id' => $comment->id,
            'user_id' => $user->id,
            'reason' => $reason,
            'description' => $description,
            'ip_address' => request()->ip(),
        ]);
        
        $comment->increment('abuse_reports_count');
        
        if (config('comments.moderation.auto_hide_on_abuse') && 
            $comment->abuse_reports_count >= config('comments.limits.abuse_threshold')) {
            $comment->update(['status' => 'hidden']);
        }
        
        event(new \Coderden\Comments\Events\CommentReported($comment, $report));
        
        return $report;
    }
    
    public function getUserReaction(Comment $comment, $user): ?array
    {
        if (!$user) return null;
        
        $reaction = CommentLike::where('comment_id', $comment->id)
            ->where('user_id', $user->id)
            ->first();
        
        if (!$reaction) return null;
        
        return [
            'type' => $reaction->type,
            'reaction_type' => $reaction->reaction_type,
            'created_at' => $reaction->created_at,
        ];
    }
    
    public function getThread(string $commentableType, int $commentableId, array $options = [])
    {
        $perPage = $options['per_page'] ?? config('comments.pagination.default_per_page');
        $sortBy = $options['sort_by'] ?? config('comments.sorting.default');
        $sortOrder = $options['sort_order'] ?? 'desc';
        
        $query = Comment::with([
            'user:id,name,avatar,email',
            'attachments',
        ])
        ->where('commentable_type', $commentableType)
        ->where('commentable_id', $commentableId)
        ->whereNull('parent_id')
        ->published();
        
        if (in_array($sortBy, config('comments.sorting.options'))) {
            $query->orderBy($sortBy, $sortOrder);
        }
        
        $query->orderBy('created_at', 'desc');
        
        return $query->paginate($perPage);
    }
    
    public function getReplies(Comment $comment, array $options = [])
    {
        $perPage = $options['per_page'] ?? 10;
        
        return Comment::with(['user:id,name,avatar'])
            ->where('parent_id', $comment->id)
            ->published()
            ->orderBy('created_at')
            ->paginate($perPage);
    }
    
    public function saveAttachments(Comment $comment, array $attachments): void
    {
        foreach ($attachments as $index => $file) {
            if ($file instanceof UploadedFile) {
                $this->validateAttachment($file);
                
                $path = $file->store(
                    config('comments.attachments.path', 'comments') . '/' . $comment->id,
                    config('comments.attachments.disk', 'public')
                );
                
                CommentAttachment::create([
                    'comment_id' => $comment->id,
                    'type' => $this->getFileType($file->getMimeType()),
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'order' => $index,
                ]);
            }
        }
    }
    
    public function updateAttachments(Comment $comment, array $attachments): void
    {
        $this->deleteAttachments($comment);
        $this->saveAttachments($comment, $attachments);
    }
    
    public function deleteAttachments(Comment $comment): void
    {
        foreach ($comment->attachments as $attachment) {
            Storage::disk(config('comments.attachments.disk', 'public'))
                ->delete($attachment->path);
            $attachment->delete();
        }
    }
    
    private function validateAttachment(UploadedFile $file): void
    {
        $maxSize = config('comments.attachments.max_size', 5120);
        $allowedMimes = config('comments.attachments.allowed_mimes', []);
        $maxFiles = config('comments.attachments.max_files', 5);
        
        if ($file->getSize() > $maxSize * 1024) {
            throw new \Exception("File size exceeds maximum allowed size of {$maxSize}KB");
        }
        
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new \Exception('File type not allowed');
        }
    }
    
    private function getFileType(string $mime): string
    {
        if (str_starts_with($mime, 'image/')) return 'image';
        if (str_starts_with($mime, 'video/')) return 'video';
        return 'file';
    }
}