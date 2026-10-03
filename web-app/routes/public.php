<?php

use App\Http\Controllers\Public\CompanyController;
use App\Http\Controllers\Public\LegalPageController;
use App\Http\Controllers\Public\OfferController;
use Illuminate\Support\Facades\Route;

/*
| Public, guest-accessible pages (offers browse, company pages).
*/

Route::name('public.')->group(function () {
    Route::get('offers', [OfferController::class, 'index'])->name('offers.index');
    Route::get('offers/{offer}', [OfferController::class, 'show'])->name('offers.show');
    Route::get('companies/{company}', [CompanyController::class, 'show'])->name('companies.show');

    Route::get('regulamin', [LegalPageController::class, 'terms'])->name('legal.terms');
    Route::get('prywatnosc', [LegalPageController::class, 'privacy'])->name('legal.privacy');
    Route::get('kontakt', [LegalPageController::class, 'contact'])->name('legal.contact');
});
