<?php

/*
| Public content (blog) and the AI legal assistant.
*/

use App\Http\Controllers\Content\AssistantController;
use App\Http\Controllers\Content\BlogController;
use Illuminate\Support\Facades\Route;

Route::get('blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('blog/{article:slug}', [BlogController::class, 'show'])->name('blog.show');

Route::middleware(['auth'])->group(function () {
    Route::get('assistant', [AssistantController::class, 'index'])->name('assistant.index');
    Route::post('assistant', [AssistantController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('assistant.store');
});
