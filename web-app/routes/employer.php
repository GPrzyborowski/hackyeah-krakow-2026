<?php

use App\Http\Controllers\Employer\CandidateController;
use App\Http\Controllers\Employer\CandidateDecisionController;
use App\Http\Controllers\Employer\CompanyController;
use App\Http\Controllers\Employer\CompanyInvitationAcceptanceController;
use App\Http\Controllers\Employer\CompanyTeamController;
use App\Http\Controllers\Employer\DashboardController;
use App\Http\Controllers\Employer\InvitationController;
use App\Http\Controllers\Employer\JobOfferController;
use App\Http\Controllers\Employer\OfferMatchPreviewController;
use App\Http\Controllers\Employer\SkillSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:employer'])->prefix('employer')->name('employer.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('company', [CompanyController::class, 'edit'])->name('company.edit');
    Route::put('company', [CompanyController::class, 'update'])->name('company.update');

    Route::get('company/team', [CompanyTeamController::class, 'index'])->name('company.team.index');
    Route::post('company/team/invitations', [CompanyTeamController::class, 'storeInvitation'])
        ->middleware('throttle:10,1,employer-team-invitations')
        ->name('company.team.invitations.store');
    Route::delete('company/team/invitations/{invitation}', [CompanyTeamController::class, 'destroyInvitation'])->name('company.team.invitations.destroy');
    Route::delete('company/team/members/{member}', [CompanyTeamController::class, 'destroyMember'])->name('company.team.members.destroy');

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

/*
| Team invitation link from the e-mail (public): guests register, signed-in employers without a company join.
*/
Route::middleware('throttle:30,1,company-invitation-acceptance')->prefix('company-invitations/{token}')->name('company-invitations.')->group(function () {
    Route::get('/', [CompanyInvitationAcceptanceController::class, 'show'])->middleware('signed')->name('show');
    Route::post('register', [CompanyInvitationAcceptanceController::class, 'register'])->middleware('guest')->name('register');
    Route::post('accept', [CompanyInvitationAcceptanceController::class, 'accept'])->middleware('auth')->name('accept');
});
