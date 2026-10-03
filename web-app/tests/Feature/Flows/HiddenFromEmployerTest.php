<?php

namespace Tests\Feature\Flows;

use App\Enums\DayPart;
use App\Enums\JobSharePairStatus;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\JobSharing\InteractsWithJobSharingFixtures;
use Tests\TestCase;

class HiddenFromEmployerTest extends TestCase
{
    use InteractsWithFlows, InteractsWithJobSharingFixtures, RefreshDatabase;

    public function test_candidate_hidden_from_her_current_employer_is_invisible_there_but_visible_to_other_companies(): void
    {
        Notification::fake();
        $recruitment = $this->skill('Rekrutacja IT');
        $currentEmployer = Company::factory()->create(['name' => 'Obecny Pracodawca']);
        $otherCompany = Company::factory()->create(['name' => 'Zielone Biuro']);
        $currentRecruiter = $this->employer($currentEmployer);
        $otherRecruiter = $this->employer($otherCompany);
        $currentOffer = $this->publishedOffer($currentEmployer, [$recruitment]);
        $otherOffer = $this->publishedOffer($otherCompany, [$recruitment]);
        $currentShareOffer = $this->jobShareOffer($currentEmployer, [$recruitment]);
        $otherShareOffer = $this->jobShareOffer($otherCompany, [$recruitment]);

        $hiding = $this->sharer([$recruitment], 'Marta Zawadzka', DayPart::Morning);
        $partner = $this->sharer([$recruitment], 'Anna Nowak', DayPart::Afternoon);
        $searcher = $this->sharer([$recruitment], 'Ola Wiśniewska', DayPart::Afternoon);
        $currentPair = $this->scheduledPair($currentShareOffer, $hiding, $partner, JobSharePairStatus::Submitted);
        $otherPair = $this->scheduledPair($otherShareOffer, $hiding, $partner, JobSharePairStatus::Submitted);

        $this->actingAs($currentRecruiter)
            ->post(route('employer.offers.candidates.decision', [$currentOffer, $hiding]), ['decision' => 'saved'])
            ->assertRedirect();
        $this->actingAs($currentRecruiter)
            ->get(route('employer.candidates.index', ['offer' => $currentOffer->id]))
            ->assertInertia(fn (Assert $page) => $page->has('saved', 1)->where('saved.0.id', $hiding->id));

        $this->actingAs($hiding->user)
            ->get(route('candidate.onboarding.show', ['step' => 4]))
            ->assertInertia(fn (Assert $page) => $page->where('companies', fn ($companies): bool => collect($companies)->contains('id', $currentEmployer->id)));
        $this->actingAs($hiding->user)
            ->patch(route('candidate.onboarding.privacy'), ['hidden_from_company_id' => $currentEmployer->id])
            ->assertRedirect();
        $this->assertSame($currentEmployer->id, $hiding->refresh()->hidden_from_company_id);

        $this->actingAs($currentRecruiter)
            ->get(route('employer.candidates.index', ['offer' => $currentOffer->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('remainingCount', 2)
                ->has('saved', 0)
                ->where('candidate.id', fn (int $id): bool => $id !== $hiding->id));
        $this->assertSwipeQueueExcludes($currentRecruiter, $currentOffer->id, $hiding->id);
        $this->actingAs($currentRecruiter)
            ->post(route('employer.offers.candidates.decision', [$currentOffer, $hiding]), ['decision' => 'saved'])
            ->assertNotFound();
        $this->actingAs($otherRecruiter)
            ->get(route('employer.candidates.index', ['offer' => $otherOffer->id]))
            ->assertInertia(fn (Assert $page) => $page->where('remainingCount', 3));

        $previewPayload = ['start_date' => '2027-09-01', 'required_skills' => ['Rekrutacja IT'], 'nice_to_have_skills' => []];
        $this->actingAs($currentRecruiter)
            ->postJson(route('employer.offers.preview-matches'), $previewPayload)
            ->assertExactJson(['with_required' => 2, 'with_nice_to_have' => 0]);
        $this->actingAs($otherRecruiter)
            ->postJson(route('employer.offers.preview-matches'), $previewPayload)
            ->assertExactJson(['with_required' => 3, 'with_nice_to_have' => 0]);

        $this->actingAs($currentRecruiter)
            ->post(route('employer.offers.candidates.invitation', [$currentOffer, $hiding]), ['message' => 'Zapraszamy na rozmowę o pracy.'])
            ->assertNotFound();
        $this->assertSame(0, Invitation::query()->count());

        $this->actingAs($currentRecruiter)
            ->get(route('employer.offers.job-share-pairs.index', $currentShareOffer))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('pairs', 0));
        $this->actingAs($currentRecruiter)
            ->post(route('employer.job-share-pairs.invitation', $currentPair), ['message' => 'Zapraszamy parę na rozmowę.'])
            ->assertNotFound();
        $this->actingAs($otherRecruiter)
            ->get(route('employer.offers.job-share-pairs.index', $otherShareOffer))
            ->assertInertia(fn (Assert $page) => $page
                ->has('pairs', 1)
                ->where('pairs.0.id', $otherPair->id));

        $this->actingAs($searcher->user)
            ->get(route('job-sharing.partners.index', $this->jobShareOffer($currentEmployer, [$recruitment])))
            ->assertInertia(fn (Assert $page) => $page
                ->where('partners', fn ($partners): bool => ! collect($partners)->contains('id', $hiding->id)
                    && collect($partners)->contains('id', $partner->id)));
        $this->actingAs($searcher->user)
            ->get(route('job-sharing.partners.index', $this->jobShareOffer($otherCompany, [$recruitment])))
            ->assertInertia(fn (Assert $page) => $page
                ->where('partners', fn ($partners): bool => collect($partners)->contains('id', $hiding->id)));
    }

    /**
     * Neither the queue, the saved list nor a direct link to her card exposes the hidden candidate.
     */
    private function assertSwipeQueueExcludes(User $recruiter, int $offerId, int $hiddenCandidateId): void
    {
        $response = $this->actingAs($recruiter)->get(route('employer.candidates.index', ['offer' => $offerId]));

        $this->assertStringNotContainsString('Marta', $this->pagePropsJson($response));
        $this->actingAs($recruiter)
            ->get(route('employer.candidates.index', ['offer' => $offerId, 'candidate' => $hiddenCandidateId]))
            ->assertInertia(fn (Assert $page) => $page->where('isSavedCandidate', false)->where('candidate.id', fn (int $id): bool => $id !== $hiddenCandidateId));
    }
}
