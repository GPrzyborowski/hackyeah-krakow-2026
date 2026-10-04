<?php

namespace Tests\Feature\JobSharing;

use App\Enums\DayPart;
use App\Models\Company;
use App\Models\JobOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JobShareOfferTest extends TestCase
{
    use InteractsWithJobSharingFixtures, RefreshDatabase;

    public function test_employer_publishes_a_job_sharing_offer_with_workday_hours()
    {
        $employer = $this->employer();

        $this->actingAs($employer)
            ->post('/employer/offers', $this->payload(['is_job_share' => true, 'workday_starts_at' => '08:00', 'workday_ends_at' => '16:00']))
            ->assertSessionHasNoErrors();

        $offer = JobOffer::sole();
        $this->assertTrue($offer->is_job_share);
        $this->assertStringStartsWith('08:00', (string) $offer->workday_starts_at);
        $this->assertStringStartsWith('16:00', (string) $offer->workday_ends_at);
    }

    public function test_job_sharing_offer_needs_a_workday_that_ends_after_it_starts()
    {
        $employer = $this->employer();

        $this->actingAs($employer)
            ->post('/employer/offers', $this->payload(['is_job_share' => true, 'workday_starts_at' => '', 'workday_ends_at' => '']))
            ->assertSessionHasErrors(['workday_starts_at', 'workday_ends_at']);

        $this->actingAs($employer)
            ->post('/employer/offers', $this->payload(['is_job_share' => true, 'workday_starts_at' => '16:00', 'workday_ends_at' => '08:00']))
            ->assertSessionHasErrors(['workday_ends_at']);

        $this->assertSame(0, JobOffer::count());
    }

    public function test_offer_lists_can_be_filtered_to_job_sharing_offers()
    {
        $company = Company::factory()->create();
        $recruitment = $this->skill('Rekrutacja IT');
        $jobShare = $this->jobShareOffer($company, [$recruitment]);
        $this->publishedOffer($company, [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');

        $this->actingAs($marta->user)
            ->get(route('candidate.offers.index', ['job_share' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('offers', 1)
                ->where('offers.0.id', $jobShare->id)
                ->where('offers.0.job_share.hours_per_person', 4));

        $this->get(route('public.offers.index', ['job_share' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('offers.data', 1)
                ->where('offers.data.0.id', $jobShare->id));
    }

    public function test_offer_detail_shows_the_job_sharing_panel_with_the_current_pair()
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $pair = $this->pair($offer, $marta, $this->sharer([$recruitment], 'Ewa Nowak'));

        $this->actingAs($marta->user)
            ->get(route('candidate.offers.show', $offer))
            ->assertInertia(fn (Assert $page) => $page
                ->where('jobSharing.workday_starts_at', '08:00')
                ->where('jobSharing.pair.id', $pair->id)
                ->where('jobSharing.pair.partner_name', 'Ewa N.'));
    }

    public function test_candidate_saves_her_preferred_part_of_the_day()
    {
        $marta = $this->sharer([$this->skill('Rekrutacja IT')], 'Marta Kowalska');

        $this->actingAs($marta->user)
            ->put(route('candidate.onboarding.preferences'), [
                'stage' => 'after_leave',
                'available_from' => '2027-09-01',
                'open_to_job_sharing' => true,
                'preferred_day_part' => 'afternoon',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(DayPart::Afternoon, $marta->fresh()?->preferred_day_part);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'action' => 'publish',
            'title' => 'Specjalistka ds. rekrutacji – job sharing',
            'city' => 'Poznań',
            'category' => 'hr',
            'work_mode' => 'hybrid',
            'start_date' => '2027-09-01',
            'description' => 'Jedno stanowisko, dwie osoby.',
            'employment_fraction' => '1/2',
            'salary_min' => 4500,
            'salary_max' => 5500,
            'flexible_hours' => true,
            'fixed_meeting_hours' => true,
            'childcare_subsidy' => false,
            'required_skills' => ['Rekrutacja IT'],
            'nice_to_have_skills' => [],
            ...$overrides,
        ];
    }
}
