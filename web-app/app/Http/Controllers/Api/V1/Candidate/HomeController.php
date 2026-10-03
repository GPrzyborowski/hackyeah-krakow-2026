<?php

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Enums\InvitationStatus;
use App\Enums\JobSharePairStatus;
use App\Http\Controllers\Candidate\Concerns\PresentsOffers;
use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ArticleResource;
use App\Http\Resources\Api\V1\HomeResource;
use App\Http\Resources\Api\V1\OfferResource;
use App\Models\Invitation;
use App\Services\Candidate\ArticleRecommendations;
use App\Services\Candidate\ReturnCalendar;
use App\Services\Matching\MatchScorer;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    use PresentsOffers, ResolvesCandidateProfile;

    private const int TOP_OFFERS = 3;

    /**
     * Candidate home: stage-specific greeting, return calendar, pending invitations, the best matching offers
     * and blog articles recommended for her stage.
     * An unpublished profile still gets a response; the app should send her to onboarding (profile.published = false).
     */
    public function __invoke(Request $request, MatchScorer $matchScorer, ReturnCalendar $returnCalendar, ArticleRecommendations $articleRecommendations): HomeResource
    {
        $profile = $this->candidateProfile($request)->load('user');

        $pendingInvitations = $profile->invitations()
            ->where('status', InvitationStatus::Pending)
            ->with('jobOffer.company')
            ->latest()
            ->get();
        $interestedOfferIds = $this->interestedOfferIds($profile);
        $savedOfferIds = $this->savedOfferIds($profile);

        return new HomeResource([
            'first_name' => strtok($profile->user->name, ' ') ?: $profile->user->name,
            'stage_message' => $profile->stage?->homeMessage(),
            'published' => $profile->isPublished(),
            'onboarding_step' => $profile->onboarding_step,
            'calendar' => $returnCalendar->forProfile($profile),
            'invitations' => [
                'pending_count' => $pendingInvitations->count(),
                'company_names' => array_values($pendingInvitations
                    ->map(fn (Invitation $invitation): string => $invitation->jobOffer->company->name)
                    ->unique()
                    ->all()),
            ],
            'pair_invitations_count' => $profile->jobSharePairs()
                ->where('status', JobSharePairStatus::Forming)
                ->wherePivotNull('accepted_at')
                ->count(),
            'saved_offers_count' => $profile->savedOffers()->published()->count(),
            'top_offers' => array_values($matchScorer->rankOffersFor($profile)
                ->take(self::TOP_OFFERS)
                ->map(fn (array $row): OfferResource => new OfferResource(
                    $row['offer'],
                    $row['match'],
                    in_array($row['offer']->id, $interestedOfferIds, true),
                    in_array($row['offer']->id, $savedOfferIds, true),
                ))
                ->all()),
            'recommended_articles' => ArticleResource::collection($articleRecommendations->forStage($profile->stage)),
        ]);
    }
}
