<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            
            // Полиморфная связь: комментарий может быть к манге, главе, странице и т.д.
            $table->morphs('commentable');
            
            // Автор комментария
            $table->unsignedBigInteger('user_id');
            
            // Для ответов на другие комментарии
            $table->foreignId('parent_id')->nullable()
                  ->constrained('comments')
                  ->onDelete('cascade');
            
            // Текст комментария
            $table->text('content');
            
            // Рейтинг (сумма лайков/дизлайков)
            $table->integer('rating')->default(0);
            
            // Счетчики для быстрого доступа
            $table->integer('likes_count')->default(0);
            $table->unsignedInteger('dislikes_count')->default(0);
            $table->unsignedInteger('replies_count')->default(0);
            $table->unsignedInteger('abuse_reports_count')->default(0);
            
            // Статус комментария
            $table->enum('status', [
                'published',    // Опубликован
                'hidden',       // Скрыт (например, из-за жалоб)
                'pending',      // На модерации
                'deleted',      // Удален (мягкое удаление)
            ])->default('published');
            
            // IP адрес пользователя (для модерации)
            $table->ipAddress('ip_address')->nullable();
            
            // User Agent (для модерации)
            $table->text('user_agent')->nullable();
            
            // Метаданные (версия приложения, платформа и т.д.)
            $table->json('meta')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Индексы
            $table->index(['commentable_type', 'commentable_id', 'status']);
            $table->index(['parent_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['rating', 'created_at']);
            $table->index('created_at');
            
            // Полнотекстовый индекс для поиска по контенту
            // \DB::statement('ALTER TABLE comments ADD FULLTEXT INDEX ft_content_search (content)');
        });
    }

    public function down()
    {
        Schema::dropIfExists('comments');
    }
};