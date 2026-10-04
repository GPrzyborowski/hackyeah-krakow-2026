<?php

namespace Tests\Feature\Api\Shared;

use App\Enums\JobSharePairStatus;
use App\Models\Company;
use App\Models\JobSharePair;
use App\Models\JobSharePairInvitation;
use App\Notifications\PairInvitationAccepted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\JobSharing\InteractsWithJobSharingFixtures;
use Tests\TestCase;

class JobSharingJoinLinkTest extends TestCase
{
    use InteractsWithJobSharingFixtures, RefreshDatabase;

    public function test_candidate_creates_an_invite_link_and_a_friend_previews_and_joins_it(): void
    {
        Notification::fake();
        $offer = $this->jobShareOffer(Company::factory()->create(), [$this->skill('Rekrutacja IT')]);
        $marta = $this->sharer([], 'Marta Kowalska');
        $ewa = $this->candidate([], ['open_to_job_sharing' => false], 'Ewa Nowak');

        Sanctum::actingAs($marta->user);
        $response = $this->postJson("/api/v1/job-sharing/offers/{$offer->id}/invite-link")->assertCreated();
        $link = JobSharePairInvitation::sole();
        $response
            ->assertJsonPath('data.token', $link->token)
            ->assertJsonPath('data.url', route('job-sharing.join.show', $link->token))
            ->assertJsonPath('data.pair_id', $link->job_share_pair_id)
            ->assertJsonPath('data.expires_at', $link->expires_at->toIso8601String());
        $this->postJson("/api/v1/job-sharing/offers/{$offer->id}/invite-link")
            ->assertOk()
            ->assertJsonPath('data.token', $link->token);
        $this->getJson("/api/v1/job-sharing/pairs/{$link->job_share_pair_id}")
            ->assertOk()
            ->assertJsonPath('data.invite_link.token', $link->token);

        Sanctum::actingAs($ewa->user);
        $this->getJson("/api/v1/job-sharing/join/{$link->token}")
            ->assertOk()
            ->assertJsonPath('data.offer.title', $offer->title)
            ->assertJsonPath('data.inviter.display_name', 'Marta K.')
            ->assertJsonPath('data.can_join', true)
            ->assertJsonPath('data.reason', null)
            ->assertDontSee('Kowalska');
        $this->postJson("/api/v1/job-sharing/join/{$link->token}")
            ->assertOk()
            ->assertJsonPath('data.status', 'formed')
            ->assertJsonPath('data.members.1.is_me', true)
            ->assertJsonPath('data.invite_link', null);

        $this->assertSame(JobSharePairStatus::Formed, JobSharePair::sole()->status);
        $this->assertTrue($ewa->fresh()?->open_to_job_sharing);
        $this->assertSame($ewa->id, $link->fresh()?->accepted_by_candidate_profile_id);
        Notification::assertSentTo($marta->user, PairInvitationAccepted::class);
    }

    public function test_invite_link_requires_a_published_profile(): void
    {
        $offer = $this->jobShareOffer(Company::factory()->create(), []);
        Sanctum::actingAs($this->sharer([], 'Marta Kowalska', attributes: ['published_at' => null])->user);

        $this->postJson("/api/v1/job-sharing/offers/{$offer->id}/invite-link")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['join_link' => 'Najpierw opublikuj swój profil, aby zaprosić kogoś do pary.']);

        $this->assertSame(0, JobSharePairInvitation::count());
    }

    public function test_preview_tells_guests_to_sign_in_and_explains_unusable_links(): void
    {
        $offer = $this->jobShareOffer(Company::factory()->create(), []);
        $marta = $this->sharer([], 'Marta Kowalska');
        Sanctum::actingAs($marta->user);
        $token = $this->postJson("/api/v1/job-sharing/offers/{$offer->id}/invite-link")->json('data.token');

        $this->getJson("/api/v1/job-sharing/join/{$token}")
            ->assertOk()
            ->assertJsonPath('data.can_join', false)
            ->assertJsonPath('data.reason', 'own_link');

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/job-sharing/join/{$token}")
            ->assertOk()
            ->assertJsonPath('data.can_join', false)
            ->assertJsonPath('data.requires_sign_in', true)
            ->assertJsonPath('data.reason', null);
        $this->getJson('/api/v1/job-sharing/join/'.str_repeat('x', 64))->assertNotFound();
    }

    public function test_joining_an_unusable_link_is_rejected(): void
    {
        $offer = $this->jobShareOffer(Company::factory()->create(), []);
        $marta = $this->sharer([], 'Marta Kowalska');
        Sanctum::actingAs($marta->user);
        $token = $this->postJson("/api/v1/job-sharing/offers/{$offer->id}/invite-link")->json('data.token');
        JobSharePairInvitation::sole()->update(['expires_at' => now()->subMinute()]);
        $ewa = $this->sharer([], 'Ewa Nowak');

        Sanctum::actingAs($ewa->user);
        $this->postJson("/api/v1/job-sharing/join/{$token}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['join_link' => 'Ten link wygasł. Poproś koleżankę o nowy link.']);

        $this->assertFalse(JobSharePair::sole()->hasMember($ewa));
    }

    public function test_employers_cannot_create_or_use_invite_links(): void
    {
        $offer = $this->jobShareOffer(Company::factory()->create(), []);
        Sanctum::actingAs($this->employer());

        $this->postJson("/api/v1/job-sharing/offers/{$offer->id}/invite-link")->assertForbidden();
        $this->postJson('/api/v1/job-sharing/join/'.str_repeat('x', 64))->assertForbidden();
    }
}
