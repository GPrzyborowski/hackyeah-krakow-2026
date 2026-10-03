<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:employer'])->prefix('employer')->name('employer.')->group(function () {
    //
});
