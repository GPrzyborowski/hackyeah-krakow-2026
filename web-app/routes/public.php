<?php

use App\Http\Controllers\Public\CompanyController;
use App\Http\Controllers\Public\OfferController;
use Illuminate\Support\Facades\Route;

/*
| Public, guest-accessible pages (offers browse, company pages).
*/

Route::name('public.')->group(function () {
    Route::get('offers', [OfferController::class, 'index'])->name('offers.index');
    Route::get('companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
});
