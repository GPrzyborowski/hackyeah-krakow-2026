<?php

namespace Tests\Feature\Conversations;

use App\Actions\Employer\InviteJobSharePair;
use App\Enums\JobSharePairStatus;
use App\Models\CandidateProfile;
use App\Models\Conversation;
use App\Models\Invitation;
use App\Models\JobSharePair;
use App\Models\User;
use App\Notifications\NewMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
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
        $offer = $this->jobShareOffer($this->recruiter->company, [], '2027-09-01');
        $this->marta = $this->sharer([], 'Marta Kowalska');
        $this->ewa = $this->sharer([], 'Ewa Nowak');
        $this->pair = $this->pair($offer, $this->marta, $this->ewa);

        app(InviteJobSharePair::class)->handle($this->pair, $this->recruiter, 'Zapraszamy Was na wspólną rozmowę.');
    }

    public function test_first_acceptance_opens_no_chat_and_reveals_nothing_until_the_partner_accepts(): void
    {
        $this->accept($this->marta);

        $this->assertSame(0, Conversation::query()->count());
        $this->actingAs($this->recruiter)
            ->get(route('employer.invitations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invitations', fn ($rows): bool => collect($rows)->every(fn (array $row): bool => ! isset($row['candidate']['full_name']) && $row['conversation_url'] === null)));
    }

    public function test_team_chat_opens_for_the_company_and_both_members_once_both_accepted(): void
    {
        $this->accept($this->marta);
        $this->accept($this->ewa);

        $teamChat = $this->teamChat();
        $this->assertSame(['Zapraszamy Was na wspólną rozmowę.'], $teamChat->messages()->pluck('body')->all());

        $this->actingAs($this->recruiter)
            ->get(route('conversations.show', $teamChat))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('conversation.is_team_chat', true)
                ->where('conversation.counterpart.type', 'team')
                ->where('conversation.counterpart.members.0.joined', true)
                ->where('conversation.counterpart.members.1.joined', true));
    }

    public function test_second_member_joins_the_same_team_chat_and_her_partner_sees_her_only_anonymously(): void
    {
        $this->accept($this->marta);
        $this->accept($this->ewa);

        $teamChat = $this->teamChat();
        $this->assertSame(JobSharePairStatus::Accepted, $this->pair->refresh()->status);
        $this->assertSame(3, Conversation::query()->count());

        $this->actingAs($this->ewa->user)
            ->post(route('conversations.messages.store', $teamChat), ['body' => 'Dzień dobry, biorę popołudnia.'])
            ->assertRedirect(route('conversations.show', $teamChat));

        $this->actingAs($this->recruiter)
            ->get(route('conversations.show', $teamChat))
            ->assertInertia(fn (Assert $page) => $page
                ->where('conversation.counterpart.name', 'Czat zespołu: Marta Kowalska i Ewa Nowak')
                ->where('messages.1.author_name', 'Ewa Nowak')
                ->where('messages.1.is_mine', false));

        $martaView = $this->actingAs($this->marta->user)->get(route('conversations.show', $teamChat));
        $martaView->assertInertia(fn (Assert $page) => $page
            ->where('messages.1.author_name', 'Ewa N.')
            ->where('messages.1.is_mine', false));
        $this->assertStringNotContainsString('Nowak', $martaView->getContent());
        $this->assertStringNotContainsString($this->ewa->user->email, $martaView->getContent());
    }

    public function test_members_of_another_company_cannot_open_the_team_chat(): void
    {
        $this->accept($this->marta);
        $this->accept($this->ewa);
        $outsider = $this->employer();

        $this->actingAs($outsider)->get(route('conversations.show', $this->teamChat()))->assertForbidden();
        $this->actingAs($outsider)
            ->post(route('conversations.messages.store', $this->teamChat()), ['body' => 'Dzień dobry'])
            ->assertForbidden();
    }

    public function test_employer_messages_in_the_team_chat_are_moderated(): void
    {
        $this->accept($this->marta);
        $this->accept($this->ewa);
        $teamChat = $this->teamChat();

        $this->actingAs($this->recruiter)
            ->post(route('conversations.messages.store', $teamChat), ['body' => 'Czy któraś z Pań planuje ciążę?'])
            ->assertSessionHasErrors(['body', 'body_suggestion']);

        $this->assertSame(1, $teamChat->messages()->count());
    }

    public function test_a_message_is_unread_and_notified_for_every_other_participant_separately(): void
    {
        $this->accept($this->marta);
        $this->accept($this->ewa);
        $teamChat = $this->teamChat();
        Notification::fake();

        $this->actingAs($this->marta->user)
            ->post(route('conversations.messages.store', $teamChat), ['body' => 'Proponuję poniedziałek.'])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($this->recruiter, NewMessage::class, fn (NewMessage $notification): bool => $notification->senderName === 'Marta Kowalska');
        Notification::assertSentTo($this->ewa->user, NewMessage::class, fn (NewMessage $notification): bool => $notification->senderName === 'Marta K.');
        Notification::assertNotSentTo($this->marta->user, NewMessage::class);

        $this->assertTeamChatListed($this->recruiter, $teamChat, hasUnread: true);
        $this->assertTeamChatListed($this->ewa->user, $teamChat, hasUnread: true);
        $this->assertTeamChatListed($this->marta->user, $teamChat, hasUnread: false);

        $this->actingAs($this->ewa->user)->get(route('conversations.show', $teamChat))->assertOk();

        $this->assertTeamChatListed($this->ewa->user, $teamChat, hasUnread: false);
        $this->assertTeamChatListed($this->recruiter, $teamChat, hasUnread: true);
    }

    private function accept(CandidateProfile $member): void
    {
        $invitation = Invitation::query()->where('job_share_pair_id', $this->pair->id)->where('candidate_profile_id', $member->id)->sole();

        $this->actingAs($member->user)->post(route('candidate.invitations.accept', $invitation))->assertRedirect();
    }

    private function teamChat(): Conversation
    {
        return Conversation::query()->where('job_share_pair_id', $this->pair->id)->sole();
    }

    private function assertTeamChatListed(User $user, Conversation $teamChat, bool $hasUnread): void
    {
        $this->actingAs($user)
            ->get(route('conversations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('conversations', fn ($rows): bool => collect($rows)
                    ->where('id', $teamChat->id)
                    ->where('is_team_chat', true)
                    ->where('has_unread', $hasUnread)
                    ->isNotEmpty()));
    }
}
