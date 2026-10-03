<?php

namespace Tests\Feature\JobSharing;

use App\Enums\DayPart;
use App\Enums\JobSharePairStatus;
use App\Models\Company;
use App\Models\JobOffer;
use App\Models\OfferInterest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PartnerSearchTest extends TestCase
{
    use InteractsWithJobSharingFixtures, RefreshDatabase;

    public function test_lists_only_eligible_job_sharing_candidates_with_interested_ones_first()
    {
        $company = Company::factory()->create();
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer($company, [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska', DayPart::Morning);

        $complementary = $this->sharer([$recruitment], 'Ewa Nowak', DayPart::Afternoon);
        $interested = $this->sharer([$recruitment], 'Anna Zielińska', DayPart::Morning);
        OfferInterest::create(['job_offer_id' => $offer->id, 'candidate_profile_id' => $interested->id]);

        $this->candidate([$recruitment], name: 'Not Open');
        $this->sharer([$this->skill('Księgowość')], 'No Skill');
        $this->sharer([$recruitment], 'Late Start', attributes: ['available_from' => '2028-01-01']);
        $this->sharer([$recruitment], 'Hidden Profile', attributes: ['hidden_from_company_id' => $company->id]);
        $this->sharer([$recruitment], 'Not Published', attributes: ['published_at' => null]);
        $paired = $this->sharer([$recruitment], 'Already Paired');
        $this->pair($offer, $paired, $this->sharer([$recruitment], 'Other Partner'));

        $this->actingAs($marta->user)
            ->get(route('job-sharing.partners.index', $offer))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('job-sharing/Partners')
                ->has('partners', 2)
                ->where('partners.0.id', $interested->id)
                ->where('partners.0.is_interested', true)
                ->where('partners.0.is_complementary', false)
                ->where('partners.1.id', $complementary->id)
                ->where('partners.1.is_complementary', true)
                ->where('partners.1.anonymous_name', 'Ewa N.')
                ->where('partners.1.preferred_day_part_label', 'Popołudnia'));
    }

    public function test_partner_list_exposes_only_anonymous_data()
    {
        $company = Company::factory()->create();
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer($company, [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $ewa = $this->sharer([$recruitment], 'Ewa Nowakowska', attributes: ['due_date' => '2027-01-23', 'leave_starts_on' => '2027-01-09']);

        $response = $this->actingAs($marta->user)->get(route('job-sharing.partners.index', $offer))->assertOk();

        $content = $response->getContent();
        $this->assertStringNotContainsString($ewa->user->email, $content);
        $this->assertStringNotContainsString('Nowakowska', $content);
        $this->assertStringNotContainsString('2027-01-23', $content);
        $this->assertStringNotContainsString('2027-01-09', $content);
    }

    public function test_candidate_with_an_active_pair_gets_no_new_partners()
    {
        $company = Company::factory()->create();
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer($company, [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $pair = $this->pair($offer, $marta, $this->sharer([$recruitment], 'Ewa Nowak'), JobSharePairStatus::Forming);
        $this->sharer([$recruitment], 'Anna Zielińska');

        $this->actingAs($marta->user)
            ->get(route('job-sharing.partners.index', $offer))
            ->assertInertia(fn (Assert $page) => $page
                ->has('partners', 0)
                ->where('activePairId', $pair->id));
    }

    public function test_partner_search_requires_a_published_job_sharing_offer_and_a_candidate()
    {
        $company = Company::factory()->create();
        $recruitment = $this->skill('Rekrutacja IT');
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $regularOffer = $this->publishedOffer($company, [$recruitment]);
        $draft = JobOffer::factory()->jobShare()->for($company)->create();

        $this->actingAs($marta->user)->get(route('job-sharing.partners.index', $regularOffer))->assertNotFound();
        $this->actingAs($marta->user)->get(route('job-sharing.partners.index', $draft))->assertNotFound();
        $this->actingAs($this->employer($company))
            ->get(route('job-sharing.partners.index', $this->jobShareOffer($company, [$recruitment])))
            ->assertForbidden();
    }
}
