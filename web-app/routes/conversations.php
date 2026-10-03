<?php

use App\Http\Controllers\Conversations\ConversationController;
use App\Http\Controllers\Conversations\MessageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::post('conversations/{conversation}/messages', [MessageController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('conversations.messages.store');
});
