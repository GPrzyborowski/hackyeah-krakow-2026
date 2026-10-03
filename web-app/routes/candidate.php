<?php

use App\Http\Controllers\Candidate\CvAnalysisController;
use App\Http\Controllers\Candidate\HomeController;
use App\Http\Controllers\Candidate\InvitationController;
use App\Http\Controllers\Candidate\OfferController;
use App\Http\Controllers\Candidate\OfferInterestController;
use App\Http\Controllers\Candidate\OnboardingController;
use App\Http\Controllers\Candidate\ProfileController;
use App\Http\Controllers\Candidate\ProfilePhotoController;
use App\Http\Controllers\Candidate\ProfileSkillController;
use App\Http\Controllers\Candidate\SavedOfferController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:candidate'])->prefix('candidate')->name('candidate.')->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('cv-analysis', CvAnalysisController::class)->name('cv-analysis');

    Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('onboarding/cv', [OnboardingController::class, 'analyzeCv'])
        ->middleware('throttle:5,1,candidate-cv')
        ->name('onboarding.cv');
    Route::patch('onboarding/summary', [OnboardingController::class, 'updateSummary'])->name('onboarding.summary');
    Route::post('onboarding/skills/confirm', [OnboardingController::class, 'confirmSkills'])->name('onboarding.skills.confirm');
    Route::put('onboarding/preferences', [OnboardingController::class, 'updatePreferences'])->name('onboarding.preferences');
    Route::patch('onboarding/privacy', [OnboardingController::class, 'updatePrivacy'])->name('onboarding.privacy');
    Route::post('onboarding/publish', [OnboardingController::class, 'publish'])->name('onboarding.publish');
    Route::post('onboarding/photo', [ProfilePhotoController::class, 'store'])
        ->middleware('throttle:10,1,candidate-photo')
        ->name('onboarding.photo.store');
    Route::delete('onboarding/photo', [ProfilePhotoController::class, 'destroy'])->name('onboarding.photo.destroy');
    Route::post('onboarding/visibility', [OnboardingController::class, 'toggleVisibility'])->name('onboarding.visibility');

    Route::get('profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('profile/preferences', [ProfileController::class, 'updatePreferences'])->name('profile.preferences');
    Route::post('profile/skills/confirm', [ProfileController::class, 'confirmSkills'])->name('profile.skills.confirm');

    Route::post('skills', [ProfileSkillController::class, 'store'])->name('skills.store');
    Route::delete('skills/{skill}', [ProfileSkillController::class, 'destroy'])->name('skills.destroy');

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
