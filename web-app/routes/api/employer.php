<?php

use App\Http\Controllers\Api\V1\Employer\CandidateController;
use App\Http\Controllers\Api\V1\Employer\CompanyController;
use App\Http\Controllers\Api\V1\Employer\DashboardController;
use App\Http\Controllers\Api\V1\Employer\InvitationController;
use App\Http\Controllers\Api\V1\Employer\JobOfferController;
use App\Http\Controllers\Api\V1\Employer\JobSharePairController;
use App\Http\Controllers\Api\V1\Employer\OfferMatchPreviewController;
use App\Http\Controllers\Api\V1\Employer\SkillController;
use App\Http\Controllers\Api\V1\Employer\TeamController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:120,1,api-user', 'role:employer', 'verified'])->prefix('employer')->name('employer.')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('company', [CompanyController::class, 'show'])->name('company.show');
    Route::put('company', [CompanyController::class, 'update'])->middleware('throttle:20,1,api-moderated-write')->name('company.update');

    Route::get('team', [TeamController::class, 'index'])->name('team.index');
    Route::post('team/invitations', [TeamController::class, 'storeInvitation'])
        ->middleware('throttle:10,1,api-employer-team-invitations')
        ->name('team.invitations.store');
    Route::delete('team/invitations/{invitation}', [TeamController::class, 'destroyInvitation'])->name('team.invitations.destroy');
    Route::delete('team/members/{user}', [TeamController::class, 'destroyMember'])->name('team.members.destroy');

    Route::get('skills', SkillController::class)
        ->middleware('throttle:60,1,api-employer-skills')
        ->name('skills.index');

    Route::post('offers/preview-matches', OfferMatchPreviewController::class)
        ->middleware('throttle:60,1,api-employer-preview-matches')
        ->name('offers.preview-matches');
    Route::get('offers', [JobOfferController::class, 'index'])->name('offers.index');
    Route::post('offers', [JobOfferController::class, 'store'])->middleware('throttle:20,1,api-moderated-write')->name('offers.store');
    Route::get('offers/{offer}', [JobOfferController::class, 'show'])->name('offers.show');
    Route::put('offers/{offer}', [JobOfferController::class, 'update'])->middleware('throttle:20,1,api-moderated-write')->name('offers.update');
    Route::post('offers/{offer}/close', [JobOfferController::class, 'close'])->name('offers.close');

    Route::get('offers/{offer}/candidates/next', [CandidateController::class, 'next'])->name('offers.candidates.next');
    Route::get('offers/{offer}/candidates/saved', [CandidateController::class, 'saved'])->name('offers.candidates.saved');
    Route::post('offers/{offer}/candidates/{candidate}/decision', [CandidateController::class, 'decide'])->name('offers.candidates.decision');
    Route::post('offers/{offer}/candidates/{candidate}/invitation', [InvitationController::class, 'store'])
        ->middleware('throttle:20,1,api-employer-invitations')
        ->name('offers.candidates.invitation');
    Route::post('offers/{offer}/candidates/{candidate}/direct-message', [InvitationController::class, 'storeDirectMessage'])
        ->middleware('throttle:20,1,api-employer-direct-messages')
        ->name('offers.candidates.direct-message');

    Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');

    Route::get('offers/{offer}/job-share-pairs', [JobSharePairController::class, 'index'])->name('offers.job-share-pairs.index');
    Route::post('job-share-pairs/{pair}/invitation', [JobSharePairController::class, 'invite'])
        ->middleware('throttle:20,1,api-employer-pair-invitations')
        ->name('job-share-pairs.invitation');
    Route::post('job-share-pairs/{pair}/reject', [JobSharePairController::class, 'reject'])->name('job-share-pairs.reject');
});
