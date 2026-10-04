<?php

use App\Http\Controllers\JobSharing\EmployerPairController;
use App\Http\Controllers\JobSharing\JoinController;
use App\Http\Controllers\JobSharing\JoinLinkController;
use App\Http\Controllers\JobSharing\PairController;
use App\Http\Controllers\JobSharing\PairMessageController;
use App\Http\Controllers\JobSharing\PairScheduleController;
use App\Http\Controllers\JobSharing\PartnerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:candidate'])->prefix('job-sharing')->name('job-sharing.')->group(function () {
    Route::get('/', [PairController::class, 'index'])->name('index');
    Route::get('offers/{offer}/partners', [PartnerController::class, 'index'])->name('partners.index');
    Route::post('offers/{offer}/pairs', [PairController::class, 'store'])
        ->middleware('throttle:20,1,job-sharing-pairs')
        ->name('pairs.store');
    Route::post('offers/{offer}/join-link', [JoinLinkController::class, 'store'])
        ->middleware('throttle:20,1,job-sharing-join-links')
        ->name('join-links.store');

    Route::get('pairs/{pair}', [PairController::class, 'show'])->name('pairs.show');
    Route::post('pairs/{pair}/accept', [PairController::class, 'accept'])->name('pairs.accept');
    Route::post('pairs/{pair}/decline', [PairController::class, 'decline'])->name('pairs.decline');
    Route::post('pairs/{pair}/cancel', [PairController::class, 'cancel'])->name('pairs.cancel');

    Route::post('pairs/{pair}/messages', [PairMessageController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('pairs.messages.store');

    Route::put('pairs/{pair}/schedule', [PairScheduleController::class, 'update'])->name('pairs.schedule.update');
    Route::post('pairs/{pair}/schedule/confirm', [PairScheduleController::class, 'confirm'])->name('pairs.schedule.confirm');
    Route::post('pairs/{pair}/submit', [PairScheduleController::class, 'submit'])->name('pairs.submit');
});

Route::middleware('throttle:30,1,job-sharing-join')->prefix('job-sharing/join/{token}')->name('job-sharing.join.')->group(function () {
    Route::get('/', [JoinController::class, 'show'])->name('show');
    Route::post('/', [JoinController::class, 'store'])->middleware(['auth', 'role:candidate'])->name('store');
});

Route::middleware(['auth', 'verified', 'role:employer'])->prefix('employer')->name('employer.')->group(function () {
    Route::get('offers/{offer}/job-share-pairs', [EmployerPairController::class, 'index'])->name('offers.job-share-pairs.index');
    Route::post('job-share-pairs/{pair}/invitation', [EmployerPairController::class, 'invite'])
        ->middleware('throttle:20,1,employer-pair-invitations')
        ->name('job-share-pairs.invitation');
    Route::post('job-share-pairs/{pair}/reject', [EmployerPairController::class, 'reject'])->name('job-share-pairs.reject');
});
