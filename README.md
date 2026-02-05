# Laravel Comments Package

A comprehensive comment system for Laravel applications with replies, likes, and abuse reporting capabilities.

## Features

- 🗨️ **Nested Comments** - Support for replies with depth limiting
- 👍 **Like/Dislike System** - With rating calculation
- 🚨 **Abuse Reporting** - Moderation system with configurable thresholds
- 📎 **Attachments** - Support for images and videos
- 🛡️ **Moderation** - Auto-hide, manual approval, and review system
- 🔍 **Search** - Full-text search capabilities
- 📊 **Statistics** - Counters and metrics
- ⚡ **Caching** - Performance optimization
- 🔌 **Extensible** - Easy to customize and extend

## Installation

```bash
composer require coderden/laravel-comments
```

Publish and run migrations:

```bash
php artisan comments:install
```

Or manually:

```bash
php artisan vendor:publish --tag=comments-config
php artisan vendor:publish --tag=comments-migrations
php artisan migrate
```

## Usage

### Add Comments to Your Models

```php
use Coderden\Comments\Traits\HasComments;

class Manga extends Model
{
    use HasComments;
    // ...
}
```

### In Your Controller

```php
use Coderden\Comments\Facades\Comments;

// Get comments for a manga
$comments = Comments::getThread('manga', $mangaId);

// Create a comment
$comment = Comments::create([
    'commentable_type' => 'manga',
    'commentable_id' => $mangaId,
    'user_id' => auth()->id(),
    'content' => 'Great manga!',
]);

// Like a comment
$result = Comments::toggleLike($comment, auth()->user(), 'like');
```

### API Endpoints

```
GET    /api/comments              List comments
POST   /api/comments              Create comment
PUT    /api/comments/{id}         Update comment
DELETE /api/comments/{id}         Delete comment
POST   /api/comments/{id}/like    Like/dislike comment
POST   /api/comments/{id}/report  Report comment
GET    /api/comments/{id}/replies Get comment replies
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=comments-config
```

Edit `config/comments.php` to customize:

- Models and table names
- Rate limiting
- Attachment settings
- Moderation rules
- Cache settings

## Events

The package fires events for important actions:

- `CommentCreated` - When a new comment is created
- `CommentLiked` - When a comment is liked/disliked
- `CommentReported` - When a comment is reported


```php
// Пример 1: Получить комментарии для манги
use Coderden\Comments\Facades\LightComments;

$comments = LightComments::getCommentsForPage(
    'manga',      // Тип сущности
    123,          // ID манги
    1,            // Страница
    20,           // Комментариев на странице
    'rating_desc' // Сортировка по рейтингу
);

// Пример 2: Создать комментарий
$result = LightComments::createComment([
    'commentable_type' => 'manga',
    'commentable_id' => 123,
    'user_id' => Auth::id(),
    'content' => 'Отличная манга!',
]);

if ($result['success']) {
    echo "Комментарий создан: " . $result['comment']->id;
}

// Пример 3: Поставить лайк
$result = LightComments::toggleLike(456, Auth::id(), 'like');

// Пример 4: Получить статистику пользователя
$stats = LightComments::getUserCommentsStats(Auth::id());
echo "Всего комментариев: " . $stats['total_comments'];
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.