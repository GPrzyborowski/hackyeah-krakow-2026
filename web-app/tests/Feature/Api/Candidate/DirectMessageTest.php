<?php

namespace Tests\Feature\Api\Candidate;

use App\Enums\InvitationStatus;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DirectMessageTest extends TestCase
{
    use RefreshDatabase;

    private CandidateProfile $profile;

    private User $employer;

    private Invitation $question;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->employer = User::factory()->employer($company)->create();
        $this->profile = CandidateProfile::factory()->published()->create(['allow_direct_messages' => true]);
        $this->question = Invitation::factory()->directMessage()
            ->for(JobOffer::factory()->published()->for($company))
            ->for($this->profile)
            ->create(['sent_by_user_id' => $this->employer->id]);
    }

    public function test_candidate_sees_the_direct_question_with_its_kind(): void
    {
        Sanctum::actingAs($this->profile->user);

        $this->getJson('/api/v1/candidate/invitations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->question->id)
            ->assertJsonPath('data.0.kind', 'direct_message')
            ->assertJsonPath('data.0.kind_label', 'Pytanie od firmy');
    }

    public function test_answering_opens_a_conversation_and_reveals_the_candidate_to_the_company(): void
    {
        Sanctum::actingAs($this->profile->user);

        $this->postJson("/api/v1/candidate/invitations/{$this->question->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.kind', 'direct_message');

        $this->assertNotNull($this->question->refresh()->conversation);

        Sanctum::actingAs($this->employer);
        $this->getJson('/api/v1/employer/invitations')
            ->assertOk()
            ->assertJsonPath('data.0.candidate.email', $this->profile->user->email);
    }

    public function test_ignoring_declines_the_question_without_revealing_the_candidate(): void
    {
        Sanctum::actingAs($this->profile->user);

        $this->postJson("/api/v1/candidate/invitations/{$this->question->id}/decline")
            ->assertOk()
            ->assertJsonPath('data.status', 'declined')
            ->assertJsonPath('data.conversation_id', null);

        $this->assertSame(InvitationStatus::Declined, $this->question->refresh()->status);

        Sanctum::actingAs($this->employer);
        $this->getJson('/api/v1/employer/invitations')
            ->assertOk()
            ->assertJsonMissing(['email' => $this->profile->user->email]);
    }
}
