<?php

use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Admin\CompanyVerificationController;
use App\Http\Controllers\Admin\LegalSourceController;
use App\Http\Controllers\Admin\ModerationEventController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('articles/preview', [ArticleController::class, 'preview'])->name('articles.preview');
    Route::resource('articles', ArticleController::class)
        ->except('show')
        ->scoped(['article' => 'id']);

    Route::resource('legal-sources', LegalSourceController::class)
        ->except('show')
        ->parameters(['legal-sources' => 'legalSource']);

    Route::get('companies', [CompanyVerificationController::class, 'index'])->name('companies.index');
    Route::post('companies/{company}/verification', [CompanyVerificationController::class, 'store'])->name('companies.verification.store');
    Route::delete('companies/{company}/verification', [CompanyVerificationController::class, 'destroy'])->name('companies.verification.destroy');

    Route::get('moderation', [ModerationEventController::class, 'index'])->name('moderation.index');
});
