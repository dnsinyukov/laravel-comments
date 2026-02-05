<?php

use Illuminate\Support\Facades\Route;
use Coderden\Comments\Http\Controllers\CommentController;

Route::name('comments.')->group(function () {
    Route::get('/', [CommentController::class, 'index'])->name('index');
    Route::get('/{comment}/replies', [CommentController::class, 'replies'])->name('replies');
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [CommentController::class, 'store'])->name('store');
        Route::put('/{comment}', [CommentController::class, 'update'])->name('update');
        Route::delete('/{comment}', [CommentController::class, 'destroy'])->name('destroy');
        Route::post('/{comment}/like', [CommentController::class, 'like'])->name('like');
        Route::post('/{comment}/report', [CommentController::class, 'report'])->name('report');
    });
});