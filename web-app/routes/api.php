<?php

use Illuminate\Support\Facades\Route;

/*
| JSON API for the mumjobs mobile app. Authenticate with a Sanctum bearer token from POST /api/v1/auth/login.
*/
Route::prefix('v1')->name('api.v1.')->group(function () {
    require __DIR__.'/api/auth.php';
    require __DIR__.'/api/candidate.php';
    require __DIR__.'/api/employer.php';
    require __DIR__.'/api/shared.php';

    if (! app()->isProduction()) {
        Route::get('openapi.yaml', fn () => response()->file(base_path('docs/api/openapi.yaml'), ['Content-Type' => 'application/yaml']))
            ->name('openapi');
    }
});
