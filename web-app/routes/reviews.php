<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ReviewModerationController;
use App\Http\Controllers\Reviews\CompanyReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:candidate'])->group(function () {
    Route::get('reviews', [CompanyReviewController::class, 'index'])->name('reviews.index');
    Route::get('reviews/companies/{company}', [CompanyReviewController::class, 'create'])->name('reviews.create');
    Route::post('reviews/companies/{company}', [CompanyReviewController::class, 'store'])->name('reviews.store');
    Route::get('reviews/{review}/edit', [CompanyReviewController::class, 'edit'])->name('reviews.edit');
    Route::put('reviews/{review}', [CompanyReviewController::class, 'update'])->name('reviews.update');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::get('reviews', [ReviewModerationController::class, 'index'])->name('reviews.index');
    Route::post('reviews/{review}/approve', [ReviewModerationController::class, 'approve'])->name('reviews.approve');
    Route::post('reviews/{review}/reject', [ReviewModerationController::class, 'reject'])->name('reviews.reject');
});
