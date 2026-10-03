<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvitationStatus;
use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Models\JobSharePair;
use App\Models\LegalSource;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Platform overview with the numbers an administrator checks first.
     */
    public function __invoke(): Response
    {
        $usersByRole = User::query()->toBase()->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'candidates' => (int) ($usersByRole[UserRole::Candidate->value] ?? 0),
                'employers' => (int) ($usersByRole[UserRole::Employer->value] ?? 0),
                'admins' => (int) ($usersByRole[UserRole::Admin->value] ?? 0),
                'published_offers' => JobOffer::query()->published()->count(),
                'unverified_companies' => Company::query()->whereNull('verified_at')->count(),
                'pending_reviews' => CompanyReview::query()->where('status', ReviewStatus::Pending)->count(),
                'accepted_invitations' => Invitation::query()->where('status', InvitationStatus::Accepted)->count(),
                'job_share_pairs' => JobSharePair::query()->count(),
                'articles' => Article::query()->count(),
                'legal_sources' => LegalSource::query()->count(),
                'newsletter_subscribers' => NewsletterSubscriber::query()
                    ->whereNotNull('confirmed_at')
                    ->whereNull('unsubscribed_at')
                    ->count(),
            ],
        ]);
    }
}
