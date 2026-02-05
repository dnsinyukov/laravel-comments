<?php

use Illuminate\Support\Facades\Route;
use Coderden\Comments\Http\Controllers\LightCommentController;

Route::prefix('api/comments/light')->name('comments.light.')->group(function () {
    // Публичные маршруты
    Route::get('/', [LightCommentController::class, 'index'])->name('index');
    Route::get('/{commentId}/thread', [LightCommentController::class, 'thread'])->name('thread');
    Route::get('/{commentId}/likes', [LightCommentController::class, 'likes'])->name('likes');
    Route::get('/{commentId}/likes-history', [LightCommentController::class, 'likesHistory'])->name('likes.history');
    
    // Защищенные маршруты
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [LightCommentController::class, 'store'])->name('store');
        Route::put('/{commentId}', [LightCommentController::class, 'update'])->name('update');
        Route::post('/{commentId}/report', [LightCommentController::class, 'report'])->name('report');
        Route::post('/{commentId}/reply', [LightCommentController::class, 'reply'])->name('reply');
        Route::post('/{commentId}/like', [LightCommentController::class, 'toggleLike'])->name('like');
        Route::get('/user/stats', [LightCommentController::class, 'userStats'])->name('user.stats');
    });
});