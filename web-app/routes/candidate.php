<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:candidate'])->prefix('candidate')->name('candidate.')->group(function () {
    //
});
