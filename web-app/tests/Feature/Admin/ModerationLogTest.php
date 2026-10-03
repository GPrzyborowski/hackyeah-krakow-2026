<?php

namespace Tests\Feature\Admin;

use App\Enums\ModerationContext;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\JobOffer;
use App\Models\ModerationEvent;
use App\Models\User;
use App\Services\Ai\MessageModerator;
use App\Services\Ai\ModerationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Employer\InteractsWithEmployerFixtures;
use Tests\TestCase;

class ModerationLogTest extends TestCase
{
    use InteractsWithEmployerFixtures, RefreshDatabase;

    private const string BLOCKED_QUESTION = 'Czy jest Pani obecnie w ciąży?';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.anthropic.key' => null]);
    }

    public function test_blocked_invitation_message_is_logged_with_company_offer_and_excerpt(): void
    {
        $company = Company::factory()->create();
        $employer = $this->employer($company);
        $offer = JobOffer::factory()->published()->for($company)->create();
        $candidate = CandidateProfile::factory()->published()->create();

        $this->actingAs($employer)
            ->post(route('employer.offers.candidates.invitation', [$offer, $candidate]), ['message' => self::BLOCKED_QUESTION])
            ->assertSessionHasErrors('message');

        $this->assertDatabaseCount('invitations', 0);
        $event = ModerationEvent::query()->sole();
        $this->assertSame(ModerationContext::Invitation, $event->context);
        $this->assertSame($employer->id, $event->user_id);
        $this->assertSame($company->id, $event->company_id);
        $this->assertSame($offer->getMorphClass(), $event->subject_type);
        $this->assertSame($offer->id, $event->subject_id);
        $this->assertSame(self::BLOCKED_QUESTION, $event->excerpt);
        $this->assertNotEmpty($event->reason);
        $this->assertNotEmpty($event->suggestion);
        $this->assertSame(ModerationEvent::MODERATOR_KEYWORD, $event->moderator);
    }

    public function test_blocked_direct_message_is_logged_as_direct_message(): void
    {
        $company = Company::factory()->create();
        $offer = JobOffer::factory()->published()->for($company)->create();
        $candidate = CandidateProfile::factory()->published()->create(['allow_direct_messages' => true]);

        $this->actingAs($this->employer($company))
            ->post(route('employer.offers.candidates.direct-message', [$offer, $candidate]), ['message' => self::BLOCKED_QUESTION])
            ->assertSessionHasErrors('message');

        $this->assertSame(ModerationContext::DirectMessage, ModerationEvent::query()->sole()->context);
    }

    public function test_blocked_chat_message_is_logged_with_the_conversation(): void
    {
        $conversation = Conversation::factory()->create();
        $employer = $this->employer($conversation->invitation->jobOffer->company);

        $this->actingAs($employer)
            ->post(route('conversations.messages.store', $conversation), ['body' => self::BLOCKED_QUESTION])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('messages', 0);
        $event = ModerationEvent::query()->sole();
        $this->assertSame(ModerationContext::ChatMessage, $event->context);
        $this->assertSame($conversation->id, $event->subject_id);
    }

    public function test_blocked_offer_title_and_description_are_logged_per_field(): void
    {
        $this->actingAs($this->employer())
            ->post(route('employer.offers.store'), [
                'title' => 'Specjalistka HR – czy masz dzieci?',
                'description' => self::BLOCKED_QUESTION,
            ])
            ->assertSessionHasErrors(['title', 'description']);

        $this->assertDatabaseCount('job_offers', 0);
        $this->assertSame(2, ModerationEvent::query()->where('context', ModerationContext::Offer)->count());
    }

    public function test_blocked_company_description_is_logged(): void
    {
        $company = Company::factory()->create();

        $this->actingAs($this->employer($company))
            ->put(route('employer.company.update'), ['name' => $company->name, 'description' => self::BLOCKED_QUESTION])
            ->assertSessionHasErrors('description');

        $event = ModerationEvent::query()->sole();
        $this->assertSame(ModerationContext::CompanyDescription, $event->context);
        $this->assertSame($company->id, $event->company_id);
    }

    public function test_blocked_candidate_summary_is_logged_without_her_text_or_a_company(): void
    {
        $candidate = User::factory()->create();
        $candidate->candidateProfile()->create(['onboarding_step' => 3]);

        $this->actingAs($candidate)
            ->patch(route('candidate.onboarding.summary'), ['ai_summary' => 'Jestem w ciąży, rodzę w marcu.'])
            ->assertSessionHasErrors('ai_summary');

        $event = ModerationEvent::query()->sole();
        $this->assertSame(ModerationContext::CandidateSummary, $event->context);
        $this->assertSame($candidate->id, $event->user_id);
        $this->assertNull($event->company_id);
        $this->assertNull($event->excerpt);
    }

    public function test_long_text_is_cut_to_the_excerpt_length(): void
    {
        $conversation = Conversation::factory()->create();

        $this->actingAs($this->employer($conversation->invitation->jobOffer->company))
            ->post(route('conversations.messages.store', $conversation), ['body' => self::BLOCKED_QUESTION.' '.str_repeat('Dzień dobry. ', 100)]);

        $excerpt = ModerationEvent::query()->sole()->excerpt;
        $this->assertLessThanOrEqual(ModerationEvent::EXCERPT_LENGTH, mb_strlen($excerpt));
        $this->assertStringEndsWith('...', $excerpt);
        $this->assertStringStartsWith(self::BLOCKED_QUESTION, $excerpt);
    }

    public function test_block_by_claude_is_attributed_to_claude(): void
    {
        $this->mock(MessageModerator::class)
            ->shouldReceive('check')
            ->andReturn(ModerationResult::block('Pośrednie pytanie o opiekę nad dziećmi.'));
        $conversation = Conversation::factory()->create();

        $this->actingAs($this->employer($conversation->invitation->jobOffer->company))
            ->post(route('conversations.messages.store', $conversation), ['body' => 'Kto odbiera maluchy po szkole?'])
            ->assertSessionHasErrors('body');

        $this->assertSame(ModerationEvent::MODERATOR_CLAUDE, ModerationEvent::query()->sole()->moderator);
    }

    public function test_allowed_texts_create_no_events(): void
    {
        $conversation = Conversation::factory()->create();
        $company = $conversation->invitation->jobOffer->company;
        $employer = $this->employer($company);

        $this->actingAs($employer)
            ->post(route('conversations.messages.store', $conversation), ['body' => 'Od kiedy może Pani zacząć?'])
            ->assertSessionHasNoErrors();
        $this->actingAs($employer)
            ->put(route('employer.company.update'), ['name' => $company->name, 'description' => 'Elastyczne godziny i praca hybrydowa.'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('moderation_events', 0);
    }

    public function test_only_admins_can_open_the_moderation_log(): void
    {
        $this->get(route('admin.moderation.index'))->assertRedirect(route('login'));

        foreach ([User::factory()->create(), $this->employer()] as $user) {
            $this->actingAs($user)->get(route('admin.moderation.index'))->assertForbidden();
        }
    }

    public function test_admin_lists_events_filters_them_and_sees_repeat_offenders(): void
    {
        $repeatOffender = Company::factory()->create(['name' => 'Alfa']);
        $other = Company::factory()->create(['name' => 'Beta']);
        ModerationEvent::factory()->forCompany($repeatOffender)->count(2)->create(['created_at' => now()->subDay()]);
        $latest = ModerationEvent::factory()->forCompany($repeatOffender)->context(ModerationContext::ChatMessage)->create();
        $otherEvent = ModerationEvent::factory()->forCompany($other)->context(ModerationContext::Offer)->create(['created_at' => now()->subHours(2)]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.moderation.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/moderation/Index')
                ->where('total', 4)
                ->has('events', 4)
                ->where('events.0.id', $latest->id)
                ->where('events.0.context', 'chat_message')
                ->where('events.0.company.name', 'Alfa')
                ->where('events.0.excerpt', $latest->excerpt)
                ->where('offenders.0.company.id', $repeatOffender->id)
                ->where('offenders.0.total', 3)
                ->where('offenders.1.company.id', $other->id)
                ->where('offenders.1.total', 1)
                ->has('companies', 2));

        $this->actingAs($admin)
            ->get(route('admin.moderation.index', ['context' => 'offer']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.context', 'offer')
                ->has('events', 1)
                ->where('events.0.id', $otherEvent->id));

        $this->actingAs($admin)
            ->get(route('admin.moderation.index', ['company' => $repeatOffender->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.company', $repeatOffender->id)
                ->where('total', 3)
                ->has('events', 3));
    }

    public function test_dashboard_counts_blocked_messages_of_the_last_seven_days(): void
    {
        ModerationEvent::factory()->count(2)->create(['created_at' => now()->subDays(6)]);
        ModerationEvent::factory()->create(['created_at' => now()->subDays(8)]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('stats.blocked_messages_week', 2));
    }
}
