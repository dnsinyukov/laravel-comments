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
    public function create(array $data, array $attachments = []): int
    {
        return DB::transaction(function () use ($data, $attachments) {
             $commentId = DB::table('comments')->insertGetId([
                'commentable_type' => $data['type'],
                'commentable_id' => $data['type_id'],
                'user_id' => $data['user_id'],
                'parent_id' => $data['parent_id'] ?? null,
                'content' => $data['content'],
                'rating' => 0,
                'likes_count' => 0,
                'dislikes_count' => 0,
                'replies_count' => 0,
                'abuse_reports_count' => 0,
                'status' => 'published',
                'ip_address' => $data['ip_address'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (!empty($data['parent_id'])) {
                DB::table('comments')->where('id', $data['parent_id'])->increment('replies_count');
            }
            
            if (!empty($attachments) && config('comments.attachments.enabled')) {
                $this->saveAttachments($commentId, $attachments);
            }
            
            event(new \Coderden\Comments\Events\CommentCreated($commentId));
            
            return $commentId;
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
    
    public function toggleLike(int $commentId, $user): array
    {
        $comment = DB::table('comments')->find($commentId, ['id']);

        return DB::transaction(function () use ($comment, $user) {
            $existing = DB::table('comment_likes')
                ->where('comment_id', $comment->id)
                ->where('user_id', $user->id)
                ->first();
            
            $action = 'none';
            
            if ($existing) {
                DB::table('comment_likes')
                    ->where('comment_id', $comment->id)
                    ->where('user_id', $user->id)
                    ->delete();

                $action = 'removed';

                DB::table('comments')->where('id', $comment->id)->decrement('likes_count');
            } else {
                $action = 'added';
                DB::table('comment_likes')
                    ->insertGetId([
                        'comment_id' => $comment->id,
                        'user_id' => $user->id,
                        'ip_address' => request()->ip(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);



                DB::table('comments')->where('id', $comment->id)->increment('likes_count');
            }
            
            event(new \Coderden\Comments\Events\CommentLiked($comment->id, $user->id,$action));
            
            return [
                'action' => $action,
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
        
        event(new \Coderden\Comments\Events\CommentReported($comment->id, $report->id));
        
        return $report;
    }
    
    
    public function getThread(string $commentableType, int $commentableId, array $options = []): array
    {
        $perPage = $options['per_page'] ?? config('comments.pagination.default_per_page');
        $sortBy = $options['sort_by'] ?? config('comments.sorting.default');
        $sortOrder = $options['sort_order'] ?? 'desc';
        
        $query = DB::table('comments', 'c')
            ->select(['c.user_id', 'c.content', 'c.rating', 'c.likes_count', 'c.replies_count', 'c.created_at', 'c.parent_id'])
            ->selectRaw('u.name, u.avatar, u.email')
            ->where('commentable_type', $commentableType)
            ->where('commentable_id', $commentableId)
            ->whereNull('parent_id')
            ->where('status', 'published')
            ->join('users as u', 'u.id', 'c.user_id');
        
        if (in_array($sortBy, config('comments.sorting.options'))) {
            $query->orderBy($sortBy, $sortOrder);
        }
        
        $query->orderBy('created_at', 'desc');
        $resultPaginator = $query->paginate($perPage);

        return [
            'items' => $resultPaginator->items(),
            'total' => $resultPaginator->total(),
            'lastPage' => $resultPaginator->lastPage()
        ];
    }
    
    public function getReplies(int $commentId, array $options = [])
    {
        $perPage = $options['per_page'] ?? 10;

        $query = DB::table('comments', 'c')
            ->select(['c.user_id', 'c.content', 'c.rating', 'c.likes_count', 'c.replies_count', 'c.created_at', 'c.parent_id'])
            ->selectRaw('u.name, u.avatar, u.email')
            ->where('parent_id', $commentId)
            ->where('status', 'published')
            ->join('users as u', 'u.id', 'c.user_id')
            ->orderBy('created_at');

        $query->orderBy('created_at', 'desc');
        $resultPaginator = $query->paginate($perPage);

        return [
            'items' => $resultPaginator->items(),
            'total' => $resultPaginator->total(),
            'lastPage' => $resultPaginator->lastPage()
        ];
    }
    
    public function saveAttachments(int $commentId, array $attachments): void
    {
        foreach ($attachments as $index => $file) {
            if ($file instanceof UploadedFile) {
                $this->validateAttachment($file);
                
                $path = $file->store(
                    config('comments.attachments.path', 'comments') . '/' . $commentId,
                    config('comments.attachments.disk', 'public')
                );
                
                CommentAttachment::create([
                    'comment_id' => $commentId,
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