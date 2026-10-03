<?php

use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Admin\LegalSourceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('articles/preview', [ArticleController::class, 'preview'])->name('articles.preview');
    Route::resource('articles', ArticleController::class)
        ->except('show')
        ->scoped(['article' => 'id']);

    Route::resource('legal-sources', LegalSourceController::class)
        ->except('show')
        ->parameters(['legal-sources' => 'legalSource']);
});
