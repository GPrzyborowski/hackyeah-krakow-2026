<?php

use App\Http\Controllers\Employer\CandidateController;
use App\Http\Controllers\Employer\CandidateDecisionController;
use App\Http\Controllers\Employer\CompanyController;
use App\Http\Controllers\Employer\InvitationController;
use App\Http\Controllers\Employer\JobOfferController;
use App\Http\Controllers\Employer\OfferMatchPreviewController;
use App\Http\Controllers\Employer\SkillSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:employer'])->prefix('employer')->name('employer.')->group(function () {
    Route::get('company', [CompanyController::class, 'edit'])->name('company.edit');
    Route::put('company', [CompanyController::class, 'update'])->name('company.update');

    Route::get('skills', SkillSearchController::class)->name('skills.index');

    Route::post('offers/preview-matches', OfferMatchPreviewController::class)->name('offers.preview-matches');
    Route::resource('offers', JobOfferController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::post('offers/{offer}/close', [JobOfferController::class, 'close'])->name('offers.close');

    Route::get('candidates', [CandidateController::class, 'index'])->name('candidates.index');
    Route::post('offers/{offer}/candidates/{candidate}/decision', [CandidateDecisionController::class, 'store'])->name('offers.candidates.decision');
    Route::post('offers/{offer}/candidates/{candidate}/invitation', [InvitationController::class, 'store'])->name('offers.candidates.invitation');

    Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');
});
