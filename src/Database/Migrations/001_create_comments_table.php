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
            
            $table->morphs('commentable');
            
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            $table->foreignId('parent_id')->nullable()
                  ->constrained('comments')
                  ->onDelete('cascade');
            
            $table->text('content');
            
            $table->integer('rating')->default(0);
            
            $table->integer('likes_count')->default(0);
            $table->unsignedInteger('dislikes_count')->default(0);
            $table->unsignedInteger('replies_count')->default(0);
            $table->unsignedInteger('abuse_reports_count')->default(0);
            
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