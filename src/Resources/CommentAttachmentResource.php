<?php

// src/Resources/CommentAttachmentResource.php

namespace Coderden\Comments\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CommentAttachmentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'url' => $this->url,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'duration' => $this->duration,
            'order' => $this->order,
            'status' => $this->status,
        ];
    }
}