<?php

use App\Http\Controllers\Employer\CandidateController;
use App\Http\Controllers\Employer\CandidateDecisionController;
use App\Http\Controllers\Employer\CompanyController;
use App\Http\Controllers\Employer\InvitationController;
use App\Http\Controllers\Employer\JobOfferController;
use App\Http\Controllers\Employer\OfferMatchPreviewController;
use App\Http\Controllers\Employer\SkillSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:employer'])->prefix('employer')->name('employer.')->group(function () {
    Route::get('company', [CompanyController::class, 'edit'])->name('company.edit');
    Route::put('company', [CompanyController::class, 'update'])->name('company.update');

    Route::get('skills', SkillSearchController::class)
        ->middleware('throttle:60,1,employer-skills')
        ->name('skills.index');

    Route::post('offers/preview-matches', OfferMatchPreviewController::class)
        ->middleware('throttle:60,1,employer-preview-matches')
        ->name('offers.preview-matches');
    Route::resource('offers', JobOfferController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::post('offers/{offer}/close', [JobOfferController::class, 'close'])->name('offers.close');

    Route::get('candidates', [CandidateController::class, 'index'])->name('candidates.index');
    Route::post('offers/{offer}/candidates/{candidate}/decision', [CandidateDecisionController::class, 'store'])->name('offers.candidates.decision');
    Route::post('offers/{offer}/candidates/{candidate}/invitation', [InvitationController::class, 'store'])
        ->middleware('throttle:20,1,employer-invitations')
        ->name('offers.candidates.invitation');
    Route::post('offers/{offer}/candidates/{candidate}/direct-message', [InvitationController::class, 'storeDirectMessage'])
        ->middleware('throttle:20,1,employer-direct-messages')
        ->name('offers.candidates.direct-message');

    Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');
});
