<?php

namespace App\Http\Controllers\Candidate;

use App\Enums\InvitationStatus;
use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Models\CandidateProfile;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Services\Matching\MatchResult;
use App\Services\Matching\MatchScorer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    use ResolvesCandidateProfile;

    private const int PREGNANCY_WEEKS = 40;

    /**
     * Candidate home: return calendar, pending invitations and the best matching offers.
     */
    public function __invoke(Request $request, MatchScorer $matchScorer): Response|RedirectResponse
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
            'calendar' => $this->returnCalendar($profile),
            'invitations' => [
                'pending_count' => $pendingInvitations->count(),
                'company_names' => $pendingInvitations
                    ->map(fn (Invitation $invitation): string => $invitation->jobOffer->company->name)
                    ->unique()
                    ->values(),
            ],
            'topOffers' => $matchScorer->rankOffersFor($profile)
                ->take(3)
                ->map(fn (array $row): array => $this->presentTopOffer($row['offer'], $row['match'])),
        ]);
    }

    /**
     * Three-phase calendar built from the candidate's private dates; any of them may be missing.
     *
     * @return array{pregnancy_week: int|null, due_date: string|null, leave_starts_on: string|null, available_from: string|null, current_phase: string}
     */
    private function returnCalendar(CandidateProfile $profile): array
    {
        $today = now()->startOfDay();
        $pregnancyWeek = null;

        if ($profile->due_date !== null && $profile->due_date->isAfter($today)) {
            $weeksLeft = (int) floor($today->diffInDays($profile->due_date) / 7);
            $pregnancyWeek = max(1, self::PREGNANCY_WEEKS - $weeksLeft);
        }

        $currentPhase = match (true) {
            $profile->available_from !== null && ! $profile->available_from->isAfter($today) => 'ready',
            $profile->leave_starts_on !== null && ! $profile->leave_starts_on->isAfter($today) => 'leave',
            $pregnancyWeek !== null => 'pregnancy',
            default => 'leave',
        };

        return [
            'pregnancy_week' => $pregnancyWeek,
            'due_date' => $profile->due_date?->toDateString(),
            'leave_starts_on' => $profile->leave_starts_on?->toDateString(),
            'available_from' => $profile->available_from?->toDateString(),
            'current_phase' => $currentPhase,
        ];
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
