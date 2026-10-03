<?php

use App\Http\Controllers\Api\V1\Candidate\CvAnalysisController;
use App\Http\Controllers\Api\V1\Candidate\HomeController;
use App\Http\Controllers\Api\V1\Candidate\InvitationController;
use App\Http\Controllers\Api\V1\Candidate\OfferController;
use App\Http\Controllers\Api\V1\Candidate\OfferInterestController;
use App\Http\Controllers\Api\V1\Candidate\ProfileController;
use App\Http\Controllers\Api\V1\Candidate\ProfilePhotoController;
use App\Http\Controllers\Api\V1\Candidate\ProfileSkillController;
use App\Http\Controllers\Api\V1\Candidate\SavedOfferController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:120,1,api-user', 'role:candidate'])->prefix('candidate')->name('candidate.')->group(function () {
    Route::get('home', HomeController::class)->name('home');
    Route::get('cv-analysis', CvAnalysisController::class)->name('cv-analysis');

    Route::prefix('profile')->name('profile.')->controller(ProfileController::class)->group(function () {
        Route::get('/', 'show')->name('show');
        Route::get('options', 'options')->name('options');
        Route::post('cv', 'analyzeCv')->middleware('throttle:5,1,api-candidate-cv')->name('cv');
        Route::post('skills/confirm', 'confirmSkills')->name('skills.confirm');
        Route::put('preferences', 'updatePreferences')->name('preferences');
        Route::patch('privacy', 'updatePrivacy')->name('privacy');
        Route::patch('summary', 'updateSummary')->middleware('throttle:20,1,api-moderated-write')->name('summary');
        Route::post('publish', 'publish')->name('publish');
        Route::post('visibility', 'updateVisibility')->name('visibility');
        Route::get('employer-preview', 'employerPreview')->name('employer-preview');
    });

    Route::post('profile/photo', [ProfilePhotoController::class, 'store'])
        ->middleware('throttle:10,1,api-candidate-photo')
        ->name('profile.photo.store');
    Route::delete('profile/photo', [ProfilePhotoController::class, 'destroy'])->name('profile.photo.destroy');

    Route::post('profile/skills', [ProfileSkillController::class, 'store'])->name('profile.skills.store');
    Route::delete('profile/skills/{skill}', [ProfileSkillController::class, 'destroy'])->name('profile.skills.destroy');

    Route::get('offers', [OfferController::class, 'index'])->name('offers.index');
    Route::get('offers/{offer}', [OfferController::class, 'show'])->name('offers.show');
    Route::post('offers/{offer}/interest', [OfferInterestController::class, 'store'])->name('offers.interest.store');
    Route::delete('offers/{offer}/interest', [OfferInterestController::class, 'destroy'])->name('offers.interest.destroy');
    Route::post('offers/{offer}/save', [SavedOfferController::class, 'store'])->name('offers.save');
    Route::delete('offers/{offer}/save', [SavedOfferController::class, 'destroy'])->name('offers.unsave');

    Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');
    Route::post('invitations/{invitation}/accept', [InvitationController::class, 'accept'])->middleware('verified')->name('invitations.accept');
    Route::post('invitations/{invitation}/decline', [InvitationController::class, 'decline'])->name('invitations.decline');
});
