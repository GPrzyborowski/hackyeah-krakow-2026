<?php

namespace Tests\Feature\Employer;

use App\Enums\InvitationStatus;
use App\Enums\OfferStatus;
use App\Models\CompanyReview;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JobOfferTest extends TestCase
{
    use InteractsWithEmployerFixtures, RefreshDatabase;

    public function test_index_lists_only_own_company_offers_with_statistics()
    {
        $employer = $this->employer();
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->publishedOffer($employer->company, [$recruitment]);
        $this->publishedOffer($this->employer()->company, [$recruitment]);
        $this->candidate([$recruitment]);
        $decided = $this->candidate([$recruitment], name: 'Anna Nowak');
        $offer->decisions()->create(['candidate_profile_id' => $decided->id, 'decision' => 'skipped']);
        Invitation::factory()->accepted()->for($offer)->create();

        $this->actingAs($employer)
            ->get('/employer/offers')
            ->assertInertia(fn (Assert $page) => $page
                ->component('employer/offers/Index')
                ->has('offers', 1)
                ->where('offers.0.id', $offer->id)
                ->where('offers.0.statistics.matched_count', 2)
                ->where('offers.0.statistics.to_review_count', 1)
                ->where('offers.0.statistics.invited_count', 1)
                ->where('offers.0.statistics.accepted_count', 1));
    }

    public function test_employer_saves_a_draft_and_creates_unknown_skills()
    {
        $employer = $this->employer();
        $this->skill('Onboarding');

        $response = $this->actingAs($employer)->post('/employer/offers', $this->payload([
            'action' => 'draft',
            'required_skills' => ['Onboarding', 'Prawo pracy'],
            'nice_to_have_skills' => ['Excel'],
        ]));

        $offer = JobOffer::sole();
        $response->assertRedirect(route('employer.offers.edit', $offer));
        $this->assertSame($employer->company_id, $offer->company_id);
        $this->assertSame(OfferStatus::Draft, $offer->status);
        $this->assertNull($offer->published_at);
        $this->assertEqualsCanonicalizing(['Onboarding', 'Prawo pracy'], $offer->requiredSkills()->pluck('name')->all());
        $this->assertSame(['Excel'], $offer->niceToHaveSkills()->pluck('name')->all());
        $this->assertSame(3, Skill::count());
    }

    public function test_publishing_redirects_to_candidates()
    {
        $employer = $this->employer();

        $response = $this->actingAs($employer)->post('/employer/offers', $this->payload(['action' => 'publish']));

        $offer = JobOffer::sole();
        $response->assertRedirect(route('employer.candidates.index', ['offer' => $offer->id]));
        $this->assertSame(OfferStatus::Published, $offer->status);
        $this->assertNotNull($offer->published_at);
    }

    public function test_employer_saves_nursery_distance_for_onsite_offers_and_validates_it()
    {
        $employer = $this->employer();

        $this->actingAs($employer)->post('/employer/offers', $this->payload(['nursery_distance_km' => 51]))
            ->assertSessionHasErrors('nursery_distance_km');

        $this->actingAs($employer)->post('/employer/offers', $this->payload(['nursery_distance_km' => 2]))
            ->assertSessionHasNoErrors();

        $offer = JobOffer::sole();
        $this->assertSame(2, $offer->nursery_distance_km);

        $this->actingAs($employer)
            ->get("/employer/offers/{$offer->id}/edit")
            ->assertInertia(fn (Assert $page) => $page->where('offer.nursery_distance_km', 2));

        $this->actingAs($employer)->put("/employer/offers/{$offer->id}", $this->payload(['work_mode' => 'remote', 'nursery_distance_km' => 2]))
            ->assertSessionHasNoErrors();

        $this->assertNull($offer->refresh()->nursery_distance_km);
    }

    public function test_publishing_requires_a_required_skill_valid_salary_range_and_future_start()
    {
        $employer = $this->employer();

        $this->actingAs($employer)
            ->post('/employer/offers', $this->payload([
                'action' => 'publish',
                'required_skills' => [],
                'salary_min' => 9000,
                'salary_max' => 8000,
                'start_date' => now()->subDay()->toDateString(),
            ]))
            ->assertSessionHasErrors(['required_skills', 'salary_max', 'start_date']);

        $this->assertSame(0, JobOffer::count());
    }

    public function test_draft_may_be_saved_without_skills()
    {
        $this->actingAs($this->employer())
            ->post('/employer/offers', $this->payload(['action' => 'draft', 'required_skills' => []]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, JobOffer::count());
    }

    public function test_description_asking_about_family_plans_is_rejected()
    {
        $this->actingAs($this->employer())
            ->post('/employer/offers', $this->payload(['description' => 'Czy planuje Pani dzieci w najbliższym czasie?']))
            ->assertSessionHasErrors('description');

        $this->assertSame(0, JobOffer::count());
    }

    public function test_title_mentioning_pregnancy_is_rejected()
    {
        $this->actingAs($this->employer())
            ->post('/employer/offers', $this->payload(['title' => 'Asystentka (nie w ciąży)']))
            ->assertSessionHasErrors('title')
            ->assertSessionDoesntHaveErrors('description');

        $this->assertSame(0, JobOffer::count());
    }

    public function test_employer_updates_own_offer_and_replaces_skills()
    {
        $employer = $this->employer();
        $offer = $this->publishedOffer($employer->company, [$this->skill('Excel')]);

        $this->actingAs($employer)
            ->put("/employer/offers/{$offer->id}", $this->payload(['action' => 'publish', 'title' => 'Księgowa']))
            ->assertRedirect();

        $offer->refresh();
        $this->assertSame('Księgowa', $offer->title);
        $this->assertSame(['Onboarding'], $offer->requiredSkills()->pluck('name')->all());
    }

    public function test_employer_cannot_view_or_edit_another_company_offer()
    {
        $employer = $this->employer();
        $foreignOffer = JobOffer::factory()->create(['title' => 'Cudza oferta']);

        $this->actingAs($employer)->get("/employer/offers/{$foreignOffer->id}/edit")->assertForbidden();
        $this->actingAs($employer)->put("/employer/offers/{$foreignOffer->id}", $this->payload())->assertForbidden();
        $this->actingAs($employer)->post("/employer/offers/{$foreignOffer->id}/close")->assertForbidden();

        $this->assertSame('Cudza oferta', $foreignOffer->refresh()->title);
    }

    public function test_edit_form_shows_offer_and_badge_inputs()
    {
        $employer = $this->employer();
        CompanyReview::factory()->for($employer->company)->create();
        $offer = $this->publishedOffer($employer->company, [$this->skill('Excel')]);

        $this->actingAs($employer)
            ->get("/employer/offers/{$offer->id}/edit")
            ->assertInertia(fn (Assert $page) => $page
                ->component('employer/offers/Form')
                ->where('offer.id', $offer->id)
                ->where('offer.required_skills', ['Excel'])
                ->where('companyHasApprovedReview', true));
    }

    public function test_closing_an_offer_withdraws_pending_invitations()
    {
        $employer = $this->employer();
        $offer = JobOffer::factory()->published()->for($employer->company)->create();
        $pending = Invitation::factory()->for($offer)->create(['status' => InvitationStatus::Pending]);
        $accepted = Invitation::factory()->accepted()->for($offer)->create();

        $this->actingAs($employer)->post("/employer/offers/{$offer->id}/close")->assertRedirect(route('employer.offers.index'));

        $this->assertSame(OfferStatus::Closed, $offer->refresh()->status);
        $this->assertSame(InvitationStatus::Withdrawn, $pending->refresh()->status);
        $this->assertSame(InvitationStatus::Accepted, $accepted->refresh()->status);
    }

    public function test_skill_autocomplete_returns_matching_skills()
    {
        $this->skill('Rekrutacja IT');
        $this->skill('Excel');

        $this->actingAs($this->employer())
            ->getJson('/employer/skills?q=rekru')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Rekrutacja IT');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'action' => 'draft',
            'title' => 'Specjalistka ds. rekrutacji',
            'city' => 'Poznań',
            'work_mode' => 'hybrid',
            'start_date' => '2027-09-01',
            'description' => 'Prowadzenie procesów rekrutacyjnych w zespołach IT.',
            'employment_fraction' => '3/5',
            'salary_min' => 8500,
            'salary_max' => 11000,
            'flexible_hours' => true,
            'fixed_meeting_hours' => true,
            'childcare_subsidy' => false,
            'required_skills' => ['Onboarding'],
            'nice_to_have_skills' => [],
            ...$overrides,
        ];
    }
}
