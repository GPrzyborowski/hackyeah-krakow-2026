<?php

namespace Tests\Feature\Employer;

use App\Models\CompanyInvitation;
use App\Models\User;
use App\Notifications\CompanyTeamInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyTeamTest extends TestCase
{
    use InteractsWithEmployerFixtures, RefreshDatabase;

    public function test_member_sees_colleagues_and_pending_invitations_of_own_company_only(): void
    {
        $employer = $this->employer();
        $colleague = $this->employer($employer->company);
        $this->employer();
        CompanyInvitation::factory()->for($employer->company)->create(['email' => 'nowa@firma.pl']);
        CompanyInvitation::factory()->for($employer->company)->accepted()->create();
        CompanyInvitation::factory()->create(['email' => 'obca@firma.pl']);

        $this->actingAs($employer)
            ->get(route('employer.company.team.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employer/company/Team')
                ->has('members', 2)
                ->where('members', fn (Collection $members): bool => $members->where('is_current_user', true)->pluck('id')->all() === [$employer->id]
                    && $members->pluck('id')->contains($colleague->id))
                ->has('invitations', 1)
                ->where('invitations.0.email', 'nowa@firma.pl')
                ->missing('invitations.0.token'));
    }

    public function test_member_invites_recruiter_by_email_with_signed_link(): void
    {
        Notification::fake();
        $employer = $this->employer();

        $this->actingAs($employer)
            ->post(route('employer.company.team.invitations.store'), ['email' => ' Nowa@Firma.pl '])
            ->assertRedirect(route('employer.company.team.index'));

        $invitation = CompanyInvitation::sole();
        $this->assertSame('nowa@firma.pl', $invitation->email);
        $this->assertSame($employer->company_id, $invitation->company_id);
        $this->assertSame($employer->id, $invitation->invited_by_user_id);
        $this->assertTrue($invitation->expires_at->isFuture());

        Notification::assertSentOnDemand(CompanyTeamInvitation::class, function (CompanyTeamInvitation $notification, array $channels, AnonymousNotifiable $notifiable) use ($invitation): bool {
            return $notifiable->routes['mail'] === 'nowa@firma.pl'
                && $notification->invitation->is($invitation)
                && str_contains($notification->url(), $invitation->token)
                && str_contains($notification->url(), 'signature=');
        });
    }

    public function test_duplicate_pending_invitation_and_existing_member_are_rejected(): void
    {
        Notification::fake();
        $employer = $this->employer();
        $colleague = $this->employer($employer->company);
        CompanyInvitation::factory()->for($employer->company)->create(['email' => 'nowa@firma.pl']);

        $this->actingAs($employer)
            ->post(route('employer.company.team.invitations.store'), ['email' => 'nowa@firma.pl'])
            ->assertSessionHasErrors(['email' => 'Zaproszenie na ten adres już czeka na akceptację.']);

        $this->actingAs($employer)
            ->post(route('employer.company.team.invitations.store'), ['email' => strtoupper($colleague->email)])
            ->assertSessionHasErrors(['email' => 'Ta osoba już należy do Twojego zespołu.']);

        Notification::assertNothingSent();
    }

    public function test_reinviting_after_expiry_replaces_the_stale_invitation(): void
    {
        Notification::fake();
        $employer = $this->employer();
        $expired = CompanyInvitation::factory()->for($employer->company)->expired()->create(['email' => 'nowa@firma.pl']);

        $this->actingAs($employer)
            ->post(route('employer.company.team.invitations.store'), ['email' => 'nowa@firma.pl'])
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($expired);
        $this->assertTrue(CompanyInvitation::sole()->expires_at->isFuture());
    }

    public function test_member_revokes_pending_invitation_but_not_another_companys(): void
    {
        $employer = $this->employer();
        $invitation = CompanyInvitation::factory()->for($employer->company)->create();
        $foreign = CompanyInvitation::factory()->create();

        $this->actingAs($employer)
            ->delete(route('employer.company.team.invitations.destroy', $foreign))
            ->assertForbidden();

        $this->actingAs($employer)
            ->delete(route('employer.company.team.invitations.destroy', $invitation))
            ->assertRedirect(route('employer.company.team.index'));

        $this->assertModelMissing($invitation);
        $this->assertModelExists($foreign);
    }

    public function test_removing_a_member_detaches_them_and_revokes_api_tokens(): void
    {
        $employer = $this->employer();
        $colleague = $this->employer($employer->company);
        $colleague->createToken('iphone');

        $this->actingAs($employer)
            ->delete(route('employer.company.team.members.destroy', $colleague))
            ->assertRedirect(route('employer.company.team.index'));

        $this->assertNull($colleague->refresh()->company_id);
        $this->assertSame(0, $colleague->tokens()->count());
        $this->actingAs($colleague)->get(route('employer.offers.index'))->assertForbidden();
    }

    public function test_member_cannot_remove_themselves_or_another_companys_recruiter(): void
    {
        $employer = $this->employer();
        $this->employer($employer->company);
        $stranger = $this->employer();

        $this->actingAs($employer)
            ->delete(route('employer.company.team.members.destroy', $employer))
            ->assertForbidden();

        $this->actingAs($employer)
            ->delete(route('employer.company.team.members.destroy', $stranger))
            ->assertForbidden();

        $this->assertSame($employer->company_id, $employer->refresh()->company_id);
        $this->assertNotNull($stranger->refresh()->company_id);
    }

    public function test_team_page_requires_an_employer_with_a_company(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('employer.company.team.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->employer()->create(['company_id' => null]))
            ->get(route('employer.company.team.index'))
            ->assertForbidden();
    }
}
