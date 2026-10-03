<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\Invitation;
use App\Notifications\CompanyVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Employer\InteractsWithEmployerFixtures;
use Tests\TestCase;

class CompanyVerificationTest extends TestCase
{
    use InteractsWithEmployerFixtures, RefreshDatabase;

    public function test_candidate_offers_carry_the_verified_flag_and_can_be_filtered(): void
    {
        $skill = $this->skill('Księgowość');
        $candidate = $this->candidate([$skill]);
        $verifiedOffer = $this->publishedOffer(Company::factory()->verified()->create(), [$skill]);
        $otherOffer = $this->publishedOffer(Company::factory()->create(), [$skill]);
        Sanctum::actingAs($candidate->user);

        $this->getJson('/api/v1/candidate/offers?verified_only=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $verifiedOffer->id)
            ->assertJsonPath('data.0.company.verified', true)
            ->assertJsonPath('meta.filters.verified_only', true);

        $this->getJson("/api/v1/candidate/offers/{$otherOffer->id}")
            ->assertOk()
            ->assertJsonPath('data.company.verified', false);

        $this->getJson('/api/v1/candidate/offers?verified_only=maybe')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('verified_only');
    }

    public function test_public_offers_and_company_carry_the_verified_flag(): void
    {
        $verified = Company::factory()->verified()->create();
        $verifiedOffer = $this->publishedOffer($verified, []);
        $this->publishedOffer(Company::factory()->create(), []);

        $this->getJson('/api/v1/public/offers?verified_only=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.company.verified', true)
            ->assertJsonPath('meta.filters.verified_only', true);

        $this->getJson("/api/v1/public/offers/{$verifiedOffer->id}")
            ->assertOk()
            ->assertJsonPath('data.company.verified', true);

        $this->getJson("/api/v1/public/companies/{$verified->id}")
            ->assertOk()
            ->assertJsonPath('data.verified', true);
    }

    public function test_candidate_invitations_and_conversation_header_carry_the_verified_flag(): void
    {
        $candidate = $this->candidate([]);
        $offer = $this->publishedOffer(Company::factory()->verified()->create(), []);
        $invitation = Invitation::factory()->for($candidate)->for($offer)->accepted()->create();
        $conversation = Conversation::factory()->for($invitation)->create();
        Sanctum::actingAs($candidate->user);

        $this->getJson('/api/v1/candidate/invitations')
            ->assertOk()
            ->assertJsonPath('data.0.company.verified', true);

        $this->getJson("/api/v1/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.counterpart.verified', true);
    }

    public function test_employer_sees_the_verification_state_and_the_notification(): void
    {
        $company = Company::factory()->create();
        $employer = $this->employer($company);
        Sanctum::actingAs($employer);

        $this->getJson('/api/v1/employer/company')
            ->assertOk()
            ->assertJsonPath('data.verified', false)
            ->assertJsonPath('data.verified_at', null);

        $employer->notify(new CompanyVerified($company));

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.kind', 'company_verified')
            ->assertJsonPath('data.0.title', 'Twoja firma została zweryfikowana')
            ->assertJsonPath('data.0.target.company_id', $company->id);
    }
}
