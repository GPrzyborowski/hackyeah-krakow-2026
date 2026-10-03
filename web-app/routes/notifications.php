<?php

/*
| In-app notifications (bell + list) and the public blog newsletter.
*/

use App\Http\Controllers\Newsletter\NewsletterController;
use App\Http\Controllers\Notifications\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
});

Route::post('newsletter', [NewsletterController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('newsletter.store');
Route::get('newsletter/confirm/{token}', [NewsletterController::class, 'confirm'])
    ->middleware('signed')
    ->name('newsletter.confirm');
Route::get('newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])
    ->name('newsletter.unsubscribe');
