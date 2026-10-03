<?php

namespace Tests\Feature\Notifications;

use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Models\User;
use App\Notifications\InvitationAccepted;
use App\Notifications\InvitationDeclined;
use App\Notifications\InvitationReceived;
use App\Notifications\NewMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    private CandidateProfile $profile;

    private Company $company;

    private User $recruiter;

    private User $colleague;

    private JobOffer $offer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.anthropic.key' => null]);

        $this->profile = CandidateProfile::factory()->published()->create();
        $this->company = Company::factory()->create(['name' => 'Zielone Biuro']);
        $this->recruiter = User::factory()->employer($this->company)->create();
        $this->colleague = User::factory()->employer($this->company)->create();
        $this->offer = JobOffer::factory()->for($this->company)->create(['title' => 'Księgowa']);
    }

    public function test_candidate_is_notified_about_a_new_invitation_without_private_details(): void
    {
        Notification::fake();

        $invitation = Invitation::factory()->for($this->offer)->create([
            'candidate_profile_id' => $this->profile->id,
            'message' => 'Sekretna treść zaproszenia',
        ]);

        Notification::assertSentTo(
            $this->profile->user,
            InvitationReceived::class,
            function (InvitationReceived $notification, array $channels) use ($invitation): bool {
                $mail = $notification->toMail($this->profile->user);
                $data = $notification->toArray($this->profile->user);

                return $channels === ['mail', 'database']
                    && $notification->invitation->is($invitation)
                    && $data['title'] === 'Firma Zielone Biuro zaprasza Cię do rozmowy o stanowisku Księgowa'
                    && $data['url'] === route('candidate.invitations.index', absolute: false)
                    && $mail->actionUrl === route('candidate.invitations.index')
                    && ! str_contains(implode(' ', $mail->introLines), 'Sekretna');
            },
        );
        Notification::assertNotSentTo([$this->recruiter, $this->colleague], InvitationReceived::class);
    }

    public function test_company_members_learn_the_full_name_when_the_candidate_accepts(): void
    {
        $invitation = Invitation::factory()->for($this->offer)->create(['candidate_profile_id' => $this->profile->id]);
        Notification::fake();

        $this->actingAs($this->profile->user)->post(route('candidate.invitations.accept', $invitation));

        $conversation = $invitation->refresh()->conversation;
        Notification::assertSentTo(
            [$this->recruiter, $this->colleague],
            InvitationAccepted::class,
            function (InvitationAccepted $notification, array $channels) use ($conversation): bool {
                $data = $notification->toArray($this->recruiter);

                return $channels === ['mail', 'database']
                    && str_starts_with($data['title'], $this->profile->user->name.' przyjęła')
                    && $data['url'] === route('conversations.show', $conversation, absolute: false)
                    && $notification->toMail($this->recruiter)->actionUrl === route('conversations.show', $conversation);
            },
        );
        Notification::assertNotSentTo($this->profile->user, InvitationAccepted::class);
    }

    public function test_company_members_get_an_anonymous_in_app_notice_when_the_candidate_declines(): void
    {
        $invitation = Invitation::factory()->for($this->offer)->create(['candidate_profile_id' => $this->profile->id]);
        Notification::fake();

        $this->actingAs($this->profile->user)->post(route('candidate.invitations.decline', $invitation));

        Notification::assertSentTo(
            [$this->recruiter, $this->colleague],
            InvitationDeclined::class,
            function (InvitationDeclined $notification, array $channels): bool {
                $title = $notification->toArray($this->recruiter)['title'];

                return $channels === ['database']
                    && str_starts_with($title, $this->profile->anonymousName())
                    && ! str_contains($title, $this->profile->user->name);
            },
        );
        Notification::assertNotSentTo([$this->recruiter, $this->colleague], InvitationAccepted::class);
    }

    public function test_employer_message_notifies_the_candidate_once_until_she_reads_it(): void
    {
        $conversation = $this->acceptedConversation();

        $this->actingAs($this->recruiter)->post(route('conversations.messages.store', $conversation), ['body' => 'Dzień dobry, kiedy możemy porozmawiać?']);
        $this->actingAs($this->recruiter)->post(route('conversations.messages.store', $conversation), ['body' => 'Proponujemy wtorek.']);

        $notifications = $this->profile->user->notifications()->where('type', NewMessage::class)->get();
        $this->assertCount(1, $notifications);
        $this->assertSame($conversation->id, $notifications->first()->data['conversation_id']);
        $this->assertSame('Nowa wiadomość od: Zielone Biuro', $notifications->first()->data['title']);
        $this->assertSame(0, $this->recruiter->notifications()->where('type', NewMessage::class)->count());

        $notifications->first()->markAsRead();
        $this->actingAs($this->recruiter)->post(route('conversations.messages.store', $conversation), ['body' => 'Czy środa też pasuje?']);

        $this->assertSame(1, $this->profile->user->unreadNotifications()->where('type', NewMessage::class)->count());
    }

    public function test_candidate_message_notifies_every_company_member(): void
    {
        $conversation = $this->acceptedConversation();

        $this->actingAs($this->profile->user)->post(route('conversations.messages.store', $conversation), ['body' => 'Dziękuję, wtorek pasuje.']);

        foreach ([$this->recruiter, $this->colleague] as $member) {
            $notification = $member->notifications()->where('type', NewMessage::class)->sole();
            $this->assertSame('Nowa wiadomość od: '.$this->profile->user->name, $notification->data['title']);
        }
        $this->assertSame(0, $this->profile->user->notifications()->where('type', NewMessage::class)->count());
    }

    private function acceptedConversation(): Conversation
    {
        $invitation = Invitation::factory()->for($this->offer)->accepted()->create(['candidate_profile_id' => $this->profile->id]);

        return Conversation::factory()->for($invitation)->create();
    }
}
