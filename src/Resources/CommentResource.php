<?php

// src/Resources/CommentResource.php

namespace Coderden\Comments\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray($request)
    {
        $user = $request->user();
        
        return [
            'id' => $this->id,
            'content' => $this->content,
            'rating' => $this->rating,
            'likes_count' => $this->likes_count,
            'dislikes_count' => $this->dislikes_count,
            'replies_count' => $this->replies_count,
            'abuse_reports_count' => $this->abuse_reports_count,
            'status' => $this->status,
            'depth' => $this->depth,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'avatar' => $this->user->avatar,
                ];
            }),
            
            'attachments' => CommentAttachmentResource::collection(
                $this->whenLoaded('attachments')
            ),
            
            'replies' => self::collection(
                $this->whenLoaded('replies')
            ),
            
            'user_reaction' => $user ? $this->getUserReaction($user) : null,
            'can_edit' => $user ? $this->canEdit($user) : false,
            'can_delete' => $user ? $this->canDelete($user) : false,
            'is_reported' => $user ? $this->isReportedBy($user) : false,
        ];
    }
}