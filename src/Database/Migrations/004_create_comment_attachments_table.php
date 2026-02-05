<?php

// database/migrations/xxxx_xx_xx_xxxxxx_create_comment_attachments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('comment_attachments', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('comment_id')
                  ->constrained()
                  ->onDelete('cascade');
            
            $table->string('type')->default('image') // image, video, file
                  ->comment('Тип вложения');
            
            $table->string('path'); // Путь к файлу
            $table->string('original_name'); // Оригинальное имя файла
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('size')->nullable(); // Размер в байтах
            
            // Метаданные для изображений
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            
            // Для видео
            $table->unsignedInteger('duration')->nullable()
                  ->comment('Длительность в секундах для видео');
            
            // Порядок отображения
            $table->unsignedSmallInteger('order')->default(0);
            
            // Статус (например, если файл проходит модерацию)
            $table->enum('status', ['pending', 'approved', 'rejected'])
                  ->default('approved');
            
            $table->timestamps();
            
            $table->index(['comment_id', 'order']);
            $table->index('type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('comment_attachments');
    }
};