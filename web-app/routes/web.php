<?php

use App\Http\Controllers\CandidatePhotoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Public\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('candidate-photos/{profile}', CandidatePhotoController::class)->name('candidate-photos.show');
});

require __DIR__.'/candidate.php';
require __DIR__.'/employer.php';
require __DIR__.'/conversations.php';
require __DIR__.'/content.php';
require __DIR__.'/public.php';
require __DIR__.'/job-sharing.php';
require __DIR__.'/reviews.php';
require __DIR__.'/admin.php';
require __DIR__.'/notifications.php';
require __DIR__.'/settings.php';
