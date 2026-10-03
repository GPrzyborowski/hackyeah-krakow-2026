<?php

namespace Tests\Feature\Api\Shared;

use App\Enums\JobSharePairStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\JobSharing\InteractsWithJobSharingFixtures;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use InteractsWithJobSharingFixtures, RefreshDatabase;

    private Conversation $conversation;

    private User $candidate;

    private User $employer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conversation = Conversation::factory()->create(['last_message_at' => now()->subHour()]);
        $this->candidate = $this->conversation->invitation->candidateProfile->user;
        $this->employer = User::factory()->employer($this->conversation->invitation->jobOffer->company)->create();
    }

    public function test_guests_get_401(): void
    {
        $this->getJson('/api/v1/conversations')->assertUnauthorized();
    }

    public function test_candidate_lists_only_her_conversations_with_unread_count(): void
    {
        Message::factory()->for($this->conversation)->for($this->employer, 'author')->create(['body' => 'Dzień dobry!']);
        Message::factory()->for($this->conversation)->for($this->candidate, 'author')->create(['body' => 'Cześć']);
        Conversation::factory()->create();
        Sanctum::actingAs($this->candidate);

        $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->conversation->id)
            ->assertJsonPath('data.0.counterpart_name', $this->conversation->invitation->jobOffer->company->name)
            ->assertJsonPath('data.0.offer.title', $this->conversation->invitation->jobOffer->title)
            ->assertJsonPath('data.0.last_message.excerpt', 'Cześć')
            ->assertJsonPath('data.0.unread_count', 1)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_candidate_sees_the_company_header(): void
    {
        Sanctum::actingAs($this->candidate);

        $this->getJson("/api/v1/conversations/{$this->conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.counterpart.type', 'company')
            ->assertJsonPath('data.counterpart.name', $this->conversation->invitation->jobOffer->company->name)
            ->assertJsonPath('data.pair_partner_name', null);
    }

    public function test_employer_sees_revealed_candidate_and_anonymous_pair_partner(): void
    {
        $profile = $this->conversation->invitation->candidateProfile;
        $partner = $this->sharer([], 'Ewa Nowak', attributes: ['due_date' => '2027-01-15']);
        $pair = $this->pair($this->conversation->invitation->jobOffer, $profile, $partner, JobSharePairStatus::Invited);
        $this->conversation->invitation->update(['job_share_pair_id' => $pair->id]);
        Sanctum::actingAs($this->employer);

        $this->getJson("/api/v1/conversations/{$this->conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.counterpart.type', 'candidate')
            ->assertJsonPath('data.counterpart.name', $this->candidate->name)
            ->assertJsonPath('data.counterpart.email', $this->candidate->email)
            ->assertJsonPath('data.pair_partner_name', 'Ewa N.')
            ->assertDontSee('Nowak')
            ->assertDontSee($partner->user->email)
            ->assertDontSee('2027-01-15');
    }

    public function test_outsiders_get_403_and_an_empty_list(): void
    {
        foreach ([User::factory()->employer()->create(), User::factory()->create()] as $outsider) {
            Sanctum::actingAs($outsider);

            $this->getJson("/api/v1/conversations/{$this->conversation->id}")->assertForbidden();
            $this->getJson('/api/v1/conversations')->assertOk()->assertJsonCount(0, 'data');
        }
    }
}
