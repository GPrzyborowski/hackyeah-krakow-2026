<?php

use App\Http\Controllers\Api\V1\Assistant\AssistantMessageController;
use App\Http\Controllers\Api\V1\Content\ArticleController;
use App\Http\Controllers\Api\V1\Content\PublicCompanyController;
use App\Http\Controllers\Api\V1\Content\PublicOfferController;
use App\Http\Controllers\Api\V1\Conversations\ConversationController;
use App\Http\Controllers\Api\V1\Conversations\MessageController;
use App\Http\Controllers\Api\V1\Device\DeviceTokenController;
use App\Http\Controllers\Api\V1\JobSharing\JoinController;
use App\Http\Controllers\Api\V1\JobSharing\JoinLinkController;
use App\Http\Controllers\Api\V1\JobSharing\PairController;
use App\Http\Controllers\Api\V1\JobSharing\PairMessageController;
use App\Http\Controllers\Api\V1\JobSharing\PairScheduleController;
use App\Http\Controllers\Api\V1\JobSharing\PartnerController;
use App\Http\Controllers\Api\V1\Notifications\NotificationController;
use App\Http\Controllers\Api\V1\Reviews\CompanyReviewController;
use App\Http\Controllers\CandidatePhotoController;
use Illuminate\Support\Facades\Route;

/*
| Endpoints used by both roles: public content, conversations, job sharing, assistant, notifications, reviews, devices.
*/

Route::prefix('public')->name('public.')->group(function () {
    Route::get('offers', [PublicOfferController::class, 'index'])->name('offers.index');
    Route::get('offers/{offer}', [PublicOfferController::class, 'show'])->name('offers.show');
    Route::get('companies/{company}', [PublicCompanyController::class, 'show'])->name('companies.show');
});

Route::get('articles', [ArticleController::class, 'index'])->name('articles.index');
Route::get('articles/{article:slug}', [ArticleController::class, 'show'])->name('articles.show');

Route::get('job-sharing/join/{token}', [JoinController::class, 'show'])
    ->middleware('throttle:30,1,api-job-sharing-join')
    ->name('job-sharing.join.show');

Route::middleware(['auth:sanctum', 'throttle:120,1,api-user'])->group(function () {
    Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::get('conversations/{conversation}/messages', [MessageController::class, 'index'])->name('conversations.messages.index');
    Route::post('conversations/{conversation}/messages', [MessageController::class, 'store'])
        ->middleware('throttle:30,1,api-conversation-messages')
        ->name('conversations.messages.store');

    Route::get('candidate-photos/{profile}', CandidatePhotoController::class)->name('candidate-photos.show');

    Route::get('assistant/messages', [AssistantMessageController::class, 'index'])->name('assistant.messages.index');
    Route::post('assistant/messages', [AssistantMessageController::class, 'store'])
        ->middleware('throttle:10,1,api-assistant')
        ->name('assistant.messages.store');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::post('devices', [DeviceTokenController::class, 'store'])->name('devices.store');
    Route::delete('devices/{token}', [DeviceTokenController::class, 'destroy'])->name('devices.destroy');

    Route::middleware('role:candidate')->group(function () {
        Route::prefix('job-sharing')->name('job-sharing.')->group(function () {
            Route::get('/', [PairController::class, 'index'])->name('index');
            Route::get('offers/{offer}/partners', [PartnerController::class, 'index'])->name('partners.index');
            Route::post('offers/{offer}/pairs', [PairController::class, 'store'])
                ->middleware('throttle:20,1,api-job-sharing-pairs')
                ->name('pairs.store');
            Route::post('offers/{offer}/invite-link', [JoinLinkController::class, 'store'])
                ->middleware('throttle:20,1,api-job-sharing-join-links')
                ->name('invite-links.store');
            Route::post('join/{token}', [JoinController::class, 'store'])
                ->middleware('throttle:30,1,api-job-sharing-join')
                ->name('join.store');

            Route::get('pairs/{pair}', [PairController::class, 'show'])->name('pairs.show');
            Route::post('pairs/{pair}/accept', [PairController::class, 'accept'])->name('pairs.accept');
            Route::post('pairs/{pair}/decline', [PairController::class, 'decline'])->name('pairs.decline');
            Route::post('pairs/{pair}/cancel', [PairController::class, 'cancel'])->name('pairs.cancel');

            Route::get('pairs/{pair}/messages', [PairMessageController::class, 'index'])->name('pairs.messages.index');
            Route::post('pairs/{pair}/messages', [PairMessageController::class, 'store'])
                ->middleware('throttle:30,1,api-pair-messages')
                ->name('pairs.messages.store');

            Route::put('pairs/{pair}/schedule', [PairScheduleController::class, 'update'])->name('pairs.schedule.update');
            Route::post('pairs/{pair}/schedule/confirm', [PairScheduleController::class, 'confirm'])->name('pairs.schedule.confirm');
            Route::post('pairs/{pair}/submit', [PairScheduleController::class, 'submit'])->name('pairs.submit');
        });

        Route::get('reviews', [CompanyReviewController::class, 'index'])->name('reviews.index');
        Route::post('reviews/companies/{company}', [CompanyReviewController::class, 'store'])->name('reviews.store');
        Route::put('reviews/{review}', [CompanyReviewController::class, 'update'])->name('reviews.update');
    });
});
