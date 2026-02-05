<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('comment_abuse_reports', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('comment_id')
                  ->constrained()
                  ->onDelete('cascade');
            
            $table->foreignId('user_id')
                  ->constrained()
                  ->onDelete('cascade');
            
            // Причина жалобы
            $table->string('reason');
            
            // Дополнительное описание (если выбрано "другое" или нужно уточнить)
            $table->text('description')->nullable();
            
            // Статус жалобы
            $table->enum('status', [
                'pending',      // На рассмотрении
                'reviewed',     // Рассмотрено
                'accepted',     // Жалоба принята
                'rejected',     // Жалоба отклонена
                'deleted',      // Удалена
            ])->default('pending');
            
            // Решение модератора
            $table->text('moderator_note')->nullable();
            
            // Модератор, который рассмотрел жалобу
            $table->foreignId('moderator_id')->nullable()
                  ->constrained('users')
                  ->onDelete('set null');
            
            // IP адрес (для ограничения количества жалоб)
            $table->ipAddress('ip_address')->nullable();
            
            $table->timestamps();
            $table->timestamp('reviewed_at')->nullable();
            
            // Уникальность: один пользователь - одна жалоба на комментарий
            $table->unique(['comment_id', 'user_id']);
            
            $table->index(['comment_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('reason');
        });
    }

    public function down()
    {
        Schema::dropIfExists('comment_abuse_reports');
    }
};