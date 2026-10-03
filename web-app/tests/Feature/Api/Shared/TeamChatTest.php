<?php

namespace Tests\Feature\Api\Shared;

use App\Actions\Employer\InviteJobSharePair;
use App\Models\CandidateProfile;
use App\Models\Conversation;
use App\Models\Invitation;
use App\Models\JobSharePair;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\JobSharing\InteractsWithJobSharingFixtures;
use Tests\TestCase;

class TeamChatTest extends TestCase
{
    use InteractsWithJobSharingFixtures, RefreshDatabase;

    private User $recruiter;

    private CandidateProfile $marta;

    private CandidateProfile $ewa;

    private JobSharePair $pair;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.anthropic.key' => null]);

        $this->recruiter = $this->employer();
        $offer = $this->jobShareOffer($this->recruiter->company, []);
        $this->marta = $this->sharer([], 'Marta Kowalska');
        $this->ewa = $this->sharer([], 'Ewa Nowak');
        $this->pair = $this->pair($offer, $this->marta, $this->ewa);

        app(InviteJobSharePair::class)->handle($this->pair, $this->recruiter, 'Zapraszamy Was na wspólną rozmowę.');
    }

    public function test_first_acceptance_waits_for_the_partner_and_the_second_returns_the_team_chat_id(): void
    {
        Sanctum::actingAs($this->marta->user);
        $this->postJson("/api/v1/candidate/invitations/{$this->invitationOf($this->marta)->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'awaiting_partner')
            ->assertJsonPath('data.status_label', 'Czeka na partnerkę')
            ->assertJsonPath('data.conversation_id', null)
            ->assertJsonPath('data.job_share_pair.team_conversation_id', null);
        $this->assertSame(0, Conversation::query()->count());

        Sanctum::actingAs($this->recruiter);
        $this->getJson('/api/v1/employer/invitations?status=awaiting_partner')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status_label', 'Czeka na drugą osobę z pary')
            ->assertJsonMissingPath('data.0.candidate.email');

        Sanctum::actingAs($this->ewa->user);
        $response = $this->postJson("/api/v1/candidate/invitations/{$this->invitationOf($this->ewa)->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
        $teamChat = Conversation::query()->where('job_share_pair_id', $this->pair->id)->sole();
        $response->assertJsonPath('data.job_share_pair.team_conversation_id', $teamChat->id);

        Sanctum::actingAs($this->employer());
        $this->getJson("/api/v1/conversations/{$teamChat->id}")->assertForbidden();
    }

    public function test_employer_sees_the_team_chat_header_with_revealed_joined_members(): void
    {
        $teamChat = $this->acceptBoth();
        Sanctum::actingAs($this->recruiter);

        $this->getJson("/api/v1/conversations/{$teamChat->id}")
            ->assertOk()
            ->assertJsonPath('data.is_team_chat', true)
            ->assertJsonPath('data.counterpart.type', 'team')
            ->assertJsonPath('data.counterpart.name', 'Czat zespołu: Marta Kowalska i Ewa Nowak')
            ->assertJsonPath('data.counterpart.company.id', $this->recruiter->company_id)
            ->assertJsonPath('data.counterpart.members.1.email', $this->ewa->user->email)
            ->assertJsonPath('data.counterpart.members.1.joined', true)
            ->assertJsonPath('data.pair_partner_name', null);
    }

    public function test_candidates_see_each_other_only_anonymously(): void
    {
        $teamChat = $this->acceptBoth();
        Sanctum::actingAs($this->ewa->user);
        $this->postJson("/api/v1/conversations/{$teamChat->id}/messages", ['body' => 'Biorę popołudnia.'])
            ->assertCreated()
            ->assertJsonPath('data.is_mine', true);

        Sanctum::actingAs($this->marta->user);
        $header = $this->getJson("/api/v1/conversations/{$teamChat->id}")
            ->assertOk()
            ->assertJsonPath('data.counterpart.name', 'Czat zespołu: Marta K. i Ewa N.')
            ->assertJsonPath('data.counterpart.members.1.email', null);
        $messages = $this->getJson("/api/v1/conversations/{$teamChat->id}/messages")
            ->assertOk()
            ->assertJsonPath('data.0.author_name', 'Ewa N.')
            ->assertJsonPath('data.0.author_side', 'candidate')
            ->assertJsonPath('data.0.is_mine', false);

        foreach ([$header, $messages] as $response) {
            $response->assertDontSee('Nowak')->assertDontSee($this->ewa->user->email);
        }
    }

    public function test_list_shows_the_team_chat_with_separate_unread_counts(): void
    {
        $teamChat = $this->acceptBoth();
        Sanctum::actingAs($this->recruiter);
        $this->postJson("/api/v1/conversations/{$teamChat->id}/messages", ['body' => 'Kiedy możecie zacząć?'])->assertCreated();

        foreach ([$this->marta->user, $this->ewa->user] as $member) {
            Sanctum::actingAs($member);
            $this->assertSame(1, $this->teamChatRow($teamChat)['unread_count']);
        }

        $this->getJson("/api/v1/conversations/{$teamChat->id}/messages")->assertOk();
        $this->assertSame(0, $this->teamChatRow($teamChat)['unread_count']);

        Sanctum::actingAs($this->marta->user);
        $row = $this->teamChatRow($teamChat);
        $this->assertSame(1, $row['unread_count']);
        $this->assertTrue($row['is_team_chat']);
        $this->assertSame('Czat zespołu: Marta K. i Ewa N.', $row['counterpart_name']);

        Sanctum::actingAs($this->recruiter);
        $this->assertSame(0, $this->teamChatRow($teamChat)['unread_count']);
    }

    public function test_employer_messages_are_moderated(): void
    {
        $teamChat = $this->acceptBoth();
        Sanctum::actingAs($this->recruiter);

        $this->postJson("/api/v1/conversations/{$teamChat->id}/messages", ['body' => 'Czy któraś z Pań planuje ciążę?'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body', 'body_suggestion']);
    }

    private function acceptBoth(): Conversation
    {
        $this->invitationOf($this->marta)->accept();
        $this->invitationOf($this->ewa)->accept();

        return Conversation::query()->where('job_share_pair_id', $this->pair->id)->sole();
    }

    private function invitationOf(CandidateProfile $member): Invitation
    {
        return Invitation::query()->where('job_share_pair_id', $this->pair->id)->where('candidate_profile_id', $member->id)->sole();
    }

    /**
     * @return array<string, mixed>
     */
    private function teamChatRow(Conversation $teamChat): array
    {
        return collect($this->getJson('/api/v1/conversations')->assertOk()->json('data'))->firstWhere('id', $teamChat->id);
    }
}
