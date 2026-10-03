<?php

namespace Tests\Feature\Api\Shared;

use App\Enums\DayPart;
use App\Models\Company;
use App\Models\JobOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\JobSharing\InteractsWithJobSharingFixtures;
use Tests\TestCase;

class JobSharingPartnerTest extends TestCase
{
    use InteractsWithJobSharingFixtures, RefreshDatabase;

    public function test_lists_eligible_partners_anonymously(): void
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska', DayPart::Morning);
        $ewa = $this->sharer([$recruitment], 'Ewa Nowak', DayPart::Afternoon, [
            'due_date' => '2027-02-14',
            'leave_starts_on' => '2027-01-03',
            'cv_text' => 'Tajne CV Ewy',
        ]);
        $this->candidate([$recruitment], name: 'Not Open');
        Sanctum::actingAs($marta->user);

        $this->getJson("/api/v1/job-sharing/offers/{$offer->id}/partners")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ewa->id)
            ->assertJsonPath('data.0.anonymous_name', 'Ewa N.')
            ->assertJsonPath('data.0.is_complementary', true)
            ->assertJsonPath('data.0.skills.0', ['name' => 'Rekrutacja IT', 'matched' => true])
            ->assertJsonPath('meta.offer.id', $offer->id)
            ->assertJsonPath('meta.is_profile_published', true)
            ->assertJsonMissingPath('data.0.email')
            ->assertDontSee('Nowak')
            ->assertDontSee($ewa->user->email)
            ->assertDontSee('2027-02-14')
            ->assertDontSee('2027-01-03')
            ->assertDontSee('Tajne CV Ewy');
    }

    public function test_requires_a_published_job_sharing_offer_and_the_candidate_role(): void
    {
        $company = Company::factory()->create();
        $recruitment = $this->skill('Rekrutacja IT');
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $regularOffer = $this->publishedOffer($company, [$recruitment]);
        $draft = JobOffer::factory()->jobShare()->for($company)->create();

        Sanctum::actingAs($marta->user);
        $this->getJson("/api/v1/job-sharing/offers/{$regularOffer->id}/partners")->assertNotFound();
        $this->getJson("/api/v1/job-sharing/offers/{$draft->id}/partners")->assertNotFound();

        Sanctum::actingAs($this->employer($company));
        $this->getJson("/api/v1/job-sharing/offers/{$this->jobShareOffer($company, [$recruitment])->id}/partners")->assertForbidden();
    }
}
