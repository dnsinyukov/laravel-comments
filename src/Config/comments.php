<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Comment System Configuration
    |--------------------------------------------------------------------------
    */
    
    'models' => [
        'user' => '\App\Models\User',
        'comment' => \Coderden\Comments\Models\Comment::class,
        'comment_like' => \Coderden\Comments\Models\CommentLike::class,
        'abuse_report' => \Coderden\Comments\Models\CommentAbuseReport::class,
        'attachment' => \Coderden\Comments\Models\CommentAttachment::class,
    ],
    
    'table_names' => [
        'comments' => 'comments',
        'comment_likes' => 'comment_likes',
        'abuse_reports' => 'comment_abuse_reports',
        'attachments' => 'comment_attachments',
    ],
    
    'route' => [
        'prefix' => 'api/comments',
        'middleware' => ['api', 'auth:sanctum'],
        'name_prefix' => 'comments.',
    ],
    
    'limits' => [
        'daily_comments' => 50,
        'comment_length' => 5000,
        'reply_depth' => 5,
        'abuse_reports_per_day' => 5,
        'abuse_threshold' => 5,
    ],
    
    'attachments' => [
        'enabled' => true,
        'max_files' => 5,
        'max_size' => 5120, // KB
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'video/mp4',
            'video/quicktime',
            'video/x-msvideo',
        ],
        'disk' => 'public',
        'path' => 'comments',
    ],
    
    'moderation' => [
        'auto_hide_on_abuse' => true,
        'require_approval' => false,
        'approval_threshold' => 0,
        'review_timeout' => 24, // hours
    ],
    
    'cache' => [
        'enabled' => true,
        'ttl' => 3600, // seconds
        'prefix' => 'comments_',
    ],
    
    'events' => [
        \Coderden\Comments\Events\CommentCreated::class => [
            \Coderden\Comments\Listeners\SendCommentNotification::class,
        ],
        \Coderden\Comments\Events\CommentLiked::class => [],
        \Coderden\Comments\Events\CommentReported::class => [
            \Coderden\Comments\Listeners\ProcessAbuseReport::class,
        ],
    ],
    
    'pagination' => [
        'default_per_page' => 20,
        'max_per_page' => 100,
    ],
    
    'sorting' => [
        'default' => 'rating',
        'options' => ['rating', 'created_at', 'likes_count'],
    ],
];