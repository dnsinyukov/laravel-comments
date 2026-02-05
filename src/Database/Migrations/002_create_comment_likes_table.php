<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('comment_likes', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('comment_id')
                  ->constrained()
                  ->onDelete('cascade');
            
            $table->foreignId('user_id')
                  ->constrained()
                  ->onDelete('cascade');
            
            $table->enum('type', ['like', 'dislike']);
            
            // Можно добавить разные типы реакций 
            $table->string('reaction_type')->nullable()
                  ->comment('Для расширения: heart, laugh, angry и т.д.');
            
            // IP адрес (для ограничения)
            $table->ipAddress('ip_address')->nullable();
            
            $table->timestamps();
            
            // Уникальность: один пользователь - одна реакция на комментарий
            $table->unique(['comment_id', 'user_id']);
            
            $table->index(['comment_id', 'type']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('comment_likes');
    }
};