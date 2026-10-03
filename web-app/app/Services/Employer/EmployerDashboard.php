<?php

namespace App\Services\Employer;

use App\Enums\InvitationStatus;
use App\Enums\JobSharePairStatus;
use App\Enums\OfferStatus;
use App\Enums\SkillImportance;
use App\Models\CandidateDecision;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Models\JobSharePair;
use App\Models\Skill;
use App\Models\User;
use App\Notifications\NotificationPresenter;
use App\Services\Conversations\ConversationInbox;
use App\Services\Matching\MatchScorer;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * The employer "Start" screen shared by the web panel and the mobile API: KPIs, the candidate funnel per offer,
 * a to-do list and recent activity. The query count does not grow with the number of offers or candidates.
 */
class EmployerDashboard
{
    public const int ACTIVITY_LIMIT = 10;

    /**
     * Ids an activity item may point to, so the mobile app can open the right screen instead of the web `url`.
     */
    private const array ACTIVITY_TARGET_KEYS = ['conversation_id', 'invitation_id', 'job_share_pair_id', 'job_offer_id', 'company_id'];

    public const int RECENT_DAYS = 30;

    public function __construct(
        private CompanyOffers $companyOffers,
        private MatchScorer $scorer,
        private ConversationInbox $inbox,
    ) {}

    /**
     * @return array{
     *     greeting: array{first_name: string},
     *     company: array{id: int, name: string, city: string|null, verified: bool, verified_at: string|null},
     *     stats: array<string, mixed>,
     *     funnel: list<array<string, mixed>>,
     *     todo: list<array<string, mixed>>,
     *     reviews: array{approved_count: int, average_rating: float|null},
     *     activity: list<array<string, mixed>>
     * }
     */
    public function forEmployer(User $user, Company $company): array
    {
        $company->loadMissing('approvedReviews');
        $hasApprovedReview = $company->approvedReviews->isNotEmpty();

        /** @var EloquentCollection<int, JobOffer> $offers */
        $offers = $this->companyOffers->listing($company)->get();
        $publishedOffers = $offers->filter(fn (JobOffer $offer): bool => $offer->status === OfferStatus::Published)->values();

        $matchedIdsByOffer = $this->matchedCandidateIdsByOffer($company, $publishedOffers);
        $funnel = $publishedOffers->map(fn (JobOffer $offer): array => $this->funnelRow($offer, $matchedIdsByOffer[$offer->id], $hasApprovedReview))->values();

        $conversations = $this->inbox->conversationsFor($user)->get();
        $unreadConversations = $conversations->filter(fn (Conversation $conversation): bool => (int) $conversation->unread_count > 0)->values();

        return [
            'greeting' => ['first_name' => strtok($user->name, ' ') ?: $user->name],
            'company' => $this->companySummary($company),
            'stats' => $this->stats($offers, $publishedOffers, $matchedIdsByOffer, $funnel, $conversations, $hasApprovedReview),
            'funnel' => array_values($funnel->all()),
            'todo' => $this->todo($offers, $funnel, $unreadConversations, $hasApprovedReview),
            'reviews' => [
                'approved_count' => $company->approvedReviews->count(),
                'average_rating' => $company->averageRating(),
            ],
            'activity' => $this->activity($user, $company, $offers),
        ];
    }

    /**
     * Matched pool per published offer, computed from one candidate query instead of one per offer.
     * Same rule as MatchScorer::matchingCandidates(): eligible for the start date and sharing at least one offer skill.
     *
     * @param  Collection<int, JobOffer>  $publishedOffers
     * @return array<int, Collection<int, int>>
     */
    private function matchedCandidateIdsByOffer(Company $company, Collection $publishedOffers): array
    {
        if ($publishedOffers->isEmpty()) {
            return [];
        }

        /** @var CarbonInterface $latestStart */
        $latestStart = $publishedOffers->max(fn (JobOffer $offer): CarbonInterface => $offer->start_date);
        $skillIds = $publishedOffers->flatMap(fn (JobOffer $offer): array => $offer->skills->modelKeys())->unique()->values()->all();

        $candidates = $skillIds === []
            ? new EloquentCollection
            : $this->scorer->eligibleCandidates($company, $latestStart)
                ->whereHas('confirmedSkills', fn (Builder $query) => $query->whereKey($skillIds))
                ->with('confirmedSkills:id')
                ->get(['id', 'available_from']);

        return $publishedOffers->mapWithKeys(function (JobOffer $offer) use ($candidates): array {
            $offerSkillIds = $offer->skills->modelKeys();

            $matched = $candidates
                ->filter(fn (CandidateProfile $candidate): bool => $this->scorer->canStartFor($candidate, $offer->start_date)
                    && array_intersect($candidate->confirmedSkills->modelKeys(), $offerSkillIds) !== [])
                ->map(fn (CandidateProfile $candidate): int => $candidate->id)
                ->values()
                ->toBase();

            return [$offer->id => $matched];
        })->all();
    }

    /**
     * Funnel counters for one published offer: matched -> reviewed -> invited -> accepted.
     *
     * @param  Collection<int, int>  $matchedIds
     * @return array<string, mixed>
     */
    private function funnelRow(JobOffer $offer, Collection $matchedIds, bool $hasApprovedReview): array
    {
        $reviewedIds = $offer->decisions->toBase()->map(fn (CandidateDecision $decision): int => $decision->candidate_profile_id)
            ->merge($offer->invitations->toBase()->map(fn (Invitation $invitation): int => $invitation->candidate_profile_id))
            ->unique();

        return [
            'offer_id' => $offer->id,
            'title' => $offer->title,
            'is_job_share' => $offer->is_job_share,
            'is_parent_friendly' => $this->companyOffers->isParentFriendly($offer, $hasApprovedReview),
            'matched_count' => $matchedIds->count(),
            'reviewed_count' => $reviewedIds->count(),
            'to_review_count' => $matchedIds->diff($reviewedIds)->count(),
            'invited_count' => $offer->invitations->count(),
            'responded_count' => $offer->invitations->whereNotNull('responded_at')->count(),
            'accepted_count' => $offer->invitations->where('status', InvitationStatus::Accepted)->count(),
            'submitted_pairs_count' => (int) $offer->getAttribute('submitted_pairs_count'),
        ];
    }

    /**
     * @return array{id: int, name: string, city: string|null, verified: bool, verified_at: string|null}
     */
    private function companySummary(Company $company): array
    {
        $verifiedAt = $company->getAttribute('verified_at');

        return [
            'id' => $company->id,
            'name' => $company->name,
            'city' => $company->city,
            'verified' => $verifiedAt !== null,
            'verified_at' => $verifiedAt instanceof CarbonInterface ? $verifiedAt->toIso8601String() : null,
        ];
    }

    /**
     * @param  Collection<int, JobOffer>  $offers
     * @param  Collection<int, JobOffer>  $publishedOffers
     * @param  array<int, Collection<int, int>>  $matchedIdsByOffer
     * @param  Collection<int, array<string, mixed>>  $funnel
     * @param  Collection<int, Conversation>  $conversations
     * @return array<string, mixed>
     */
    private function stats(Collection $offers, Collection $publishedOffers, array $matchedIdsByOffer, Collection $funnel, Collection $conversations, bool $hasApprovedReview): array
    {
        $invitations = $offers->flatMap(fn (JobOffer $offer): array => $offer->invitations->all());
        $recentSince = now()->subDays(self::RECENT_DAYS);
        $respondedCount = $invitations->whereNotNull('responded_at')->count();
        $acceptedCount = $invitations->where('status', InvitationStatus::Accepted)->count();

        return [
            'published_offers_count' => $publishedOffers->count(),
            'matching_candidates_count' => collect($matchedIdsByOffer)->flatten()->unique()->count(),
            'to_review_count' => (int) $funnel->sum('to_review_count'),
            'invitations_sent_recent_count' => $invitations->filter(fn (Invitation $invitation): bool => $invitation->created_at->gte($recentSince))->count(),
            'recent_days' => self::RECENT_DAYS,
            'responded_count' => $respondedCount,
            'accepted_count' => $acceptedCount,
            'acceptance_rate' => $respondedCount > 0 ? (int) round($acceptedCount / $respondedCount * 100) : null,
            'active_conversations_count' => $conversations->filter(fn (Conversation $conversation): bool => $conversation->last_message_at?->gte($recentSince) ?? false)->count(),
            'submitted_pairs_count' => (int) $funnel->sum('submitted_pairs_count'),
            'parent_friendly' => [
                'count' => $publishedOffers->filter(fn (JobOffer $offer): bool => $this->companyOffers->isParentFriendly($offer, $hasApprovedReview))->count(),
                'total' => $publishedOffers->count(),
            ],
        ];
    }

    /**
     * What the employer should do next: unread chats, pairs waiting, candidates to review and offers missing badge criteria.
     *
     * @param  Collection<int, JobOffer>  $offers
     * @param  Collection<int, array<string, mixed>>  $funnel
     * @param  Collection<int, Conversation>  $unreadConversations
     * @return list<array<string, mixed>>
     */
    private function todo(Collection $offers, Collection $funnel, Collection $unreadConversations, bool $hasApprovedReview): array
    {
        /** @var Collection<int, array<string, mixed>> $items */
        $items = collect();

        if ($unreadConversations->isNotEmpty()) {
            $items->push([
                'kind' => 'unread_messages',
                'count' => $unreadConversations->count(),
                'offer_id' => null,
                'offer_title' => null,
                'names' => $unreadConversations->take(3)->map(fn (Conversation $conversation): string => $conversation->candidateParticipants()->map(fn (CandidateProfile $profile): string => $profile->user->name)->implode(' i '))->values()->all(),
                'hints' => [],
                'url' => route('conversations.index', absolute: false),
            ]);
        }

        $funnel->where('submitted_pairs_count', '>', 0)->each(fn (array $row) => $items->push([
            'kind' => 'submitted_pairs',
            'count' => $row['submitted_pairs_count'],
            'offer_id' => $row['offer_id'],
            'offer_title' => $row['title'],
            'names' => [],
            'hints' => [],
            'url' => route('employer.offers.job-share-pairs.index', $row['offer_id'], absolute: false),
        ]));

        $funnel->where('to_review_count', '>', 0)->sortByDesc('to_review_count')->take(3)->each(fn (array $row) => $items->push([
            'kind' => 'candidates_to_review',
            'count' => $row['to_review_count'],
            'offer_id' => $row['offer_id'],
            'offer_title' => $row['title'],
            'names' => [],
            'hints' => [],
            'url' => route('employer.candidates.index', ['offer' => $row['offer_id']], absolute: false),
        ]));

        $offers
            ->filter(fn (JobOffer $offer): bool => $offer->status !== OfferStatus::Closed)
            ->each(function (JobOffer $offer) use ($items): void {
                $hints = $this->offerHints($offer);

                if ($hints !== []) {
                    $items->push([
                        'kind' => 'offer_incomplete',
                        'count' => count($hints),
                        'offer_id' => $offer->id,
                        'offer_title' => $offer->title,
                        'names' => [],
                        'hints' => $hints,
                        'url' => route('employer.offers.edit', $offer, absolute: false),
                    ]);
                }
            });

        if (! $hasApprovedReview) {
            $items->push([
                'kind' => 'no_approved_reviews',
                'count' => 0,
                'offer_id' => null,
                'offer_title' => null,
                'names' => [],
                'hints' => [],
                'url' => route('employer.company.edit', absolute: false),
            ]);
        }

        return array_values($items->all());
    }

    /**
     * Missing pieces that keep an offer from matching well or from the "przyjazna rodzicom" badge.
     *
     * @return list<string>
     */
    private function offerHints(JobOffer $offer): array
    {
        $hasRequiredSkills = $offer->skills->contains(function (Skill $skill): bool {
            /** @var object{importance: string}|null $pivot */
            $pivot = $skill->getRelationValue('pivot');

            return $pivot !== null && $pivot->importance === SkillImportance::Required->value;
        });

        return array_values(array_filter([
            $hasRequiredSkills ? null : 'missing_required_skills',
            $offer->salary_min === null || $offer->salary_max === null ? 'missing_salary' : null,
            $offer->flexible_hours ? null : 'no_flexible_hours',
        ]));
    }

    /**
     * Latest notifications (invitation answers, new messages, hired pairs) merged with job-share pair submissions.
     *
     * @param  Collection<int, JobOffer>  $offers
     * @return list<array{id: string, kind: string|null, title: string, body: string|null, url: string|null, read: bool, created_at: string|null, created_at_diff: string|null, target: object}>
     */
    private function activity(User $user, Company $company, Collection $offers): array
    {
        $notifications = $user->notifications()
            ->limit(self::ACTIVITY_LIMIT)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => [
                ...NotificationPresenter::present($notification),
                'target' => (object) array_intersect_key((array) $notification->data, array_flip(self::ACTIVITY_TARGET_KEYS)),
            ]);

        $offerTitles = $offers->pluck('title', 'id');

        $submittedPairs = $offerTitles->isEmpty() ? collect() : JobSharePair::query()
            ->whereIn('job_offer_id', $offerTitles->keys())
            ->whereNotNull('submitted_at')
            ->whereNot('status', JobSharePairStatus::Cancelled)
            ->visibleToCompany($company)
            ->latest('submitted_at')
            ->limit(self::ACTIVITY_LIMIT)
            ->get(['id', 'job_offer_id', 'status', 'submitted_at'])
            ->map(fn (JobSharePair $pair): array => [
                'id' => 'pair-'.$pair->id,
                'kind' => 'pair_submitted',
                'title' => 'Para kandydatek zgłosiła się do oferty '.$offerTitles[$pair->job_offer_id],
                'body' => null,
                'url' => route('employer.offers.job-share-pairs.index', $pair->job_offer_id, absolute: false),
                'read' => $pair->status !== JobSharePairStatus::Submitted,
                'created_at' => $pair->submitted_at?->toIso8601String(),
                'created_at_diff' => $pair->submitted_at?->diffForHumans(),
                'target' => (object) ['job_share_pair_id' => $pair->id, 'job_offer_id' => $pair->job_offer_id],
            ]);

        return array_values($notifications->toBase()
            ->merge($submittedPairs)
            ->sortByDesc(fn (array $item): string => (string) $item['created_at'])
            ->take(self::ACTIVITY_LIMIT)
            ->values()
            ->all());
    }
}
