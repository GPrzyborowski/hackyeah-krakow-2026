<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Send each role to its own home screen.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        return match ($user->role) {
            UserRole::Candidate => $user->candidateProfile?->isPublished()
                ? redirect('/candidate')
                : redirect('/candidate/onboarding'),
            UserRole::Employer => redirect('/employer/offers'),
            UserRole::Admin => redirect('/admin'),
        };
    }
}
