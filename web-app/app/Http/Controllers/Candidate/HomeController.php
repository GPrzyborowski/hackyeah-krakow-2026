<?php

namespace App\Http\Controllers\Candidate;

use App\Enums\InvitationStatus;
use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Services\Candidate\ArticleRecommendations;
use App\Services\Candidate\ReturnCalendar;
use App\Services\JobSharing\CandidatePairOverview;
use App\Services\Matching\MatchResult;
use App\Services\Matching\MatchScorer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    use ResolvesCandidateProfile;

    /**
     * Candidate home: stage-specific greeting, return calendar, pending invitations, her job-sharing pairs,
     * the best matching offers and blog articles for her stage.
     */
    public function __invoke(Request $request, MatchScorer $matchScorer, ReturnCalendar $returnCalendar, ArticleRecommendations $articleRecommendations, CandidatePairOverview $pairOverview): Response|RedirectResponse
    {
        $profile = $this->candidateProfile($request)->load('user');

        if (! $profile->isPublished()) {
            return to_route('candidate.onboarding.show');
        }

        $pendingInvitations = $profile->invitations()
            ->where('status', InvitationStatus::Pending)
            ->with('jobOffer.company')
            ->latest()
            ->get();

        return Inertia::render('candidate/Home', [
            'firstName' => strtok($profile->user->name, ' ') ?: $profile->user->name,
            'stageMessage' => $profile->stage?->homeMessage(),
            'calendar' => $returnCalendar->forProfile($profile),
            'invitations' => [
                'pending_count' => $pendingInvitations->count(),
                'company_names' => $pendingInvitations
                    ->map(fn (Invitation $invitation): string => $invitation->jobOffer->company->name)
                    ->unique()
                    ->values(),
            ],
            'jobSharing' => $pairOverview->forProfile($profile),
            'savedOffersCount' => $profile->savedOffers()->published()->count(),
            'topOffers' => $matchScorer->rankOffersFor($profile)
                ->take(3)
                ->map(fn (array $row): array => $this->presentTopOffer($row['offer'], $row['match'])),
            'recommendedArticles' => $articleRecommendations->forStage($profile->stage)
                ->map(fn (Article $article): array => [
                    'id' => $article->id,
                    'title' => $article->title,
                    'slug' => $article->slug,
                    'excerpt' => $article->excerpt,
                    'category_label' => $article->category->label(),
                    'reading_minutes' => $article->reading_minutes,
                ]),
        ]);
    }

    /**
     * @return array{id: int, title: string, company: string, work_mode_label: string, city: string|null, score: int}
     */
    private function presentTopOffer(JobOffer $offer, MatchResult $match): array
    {
        return [
            'id' => $offer->id,
            'title' => $offer->title,
            'company' => $offer->company->name,
            'work_mode_label' => $offer->work_mode->label(),
            'city' => $offer->city,
            'score' => $match->score,
        ];
    }
}
