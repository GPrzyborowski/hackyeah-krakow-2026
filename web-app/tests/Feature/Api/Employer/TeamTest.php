<?php

namespace Tests\Feature\Api\Employer;

use App\Models\CompanyInvitation;
use App\Models\User;
use App\Notifications\CompanyTeamInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Employer\InteractsWithEmployerFixtures;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use InteractsWithEmployerFixtures, RefreshDatabase;

    public function test_employer_lists_members_and_pending_invitations_without_tokens(): void
    {
        $employer = $this->employer();
        $colleague = $this->employer($employer->company);
        $this->employer();
        $invitation = CompanyInvitation::factory()->for($employer->company)->create(['email' => 'nowa@firma.pl', 'invited_by_user_id' => $colleague->id]);
        CompanyInvitation::factory()->create(['email' => 'obca@firma.pl']);
        Sanctum::actingAs($employer);

        $this->getJson('/api/v1/employer/team')
            ->assertOk()
            ->assertJsonCount(2, 'data.members')
            ->assertJsonCount(1, 'data.invitations')
            ->assertJsonPath('data.invitations.0.email', 'nowa@firma.pl')
            ->assertJsonPath('data.invitations.0.invited_by', $colleague->name)
            ->assertJsonPath('data.invitations.0.is_expired', false)
            ->assertJsonStructure(['data' => ['members' => [['id', 'name', 'email', 'joined_at', 'is_current_user']]]])
            ->assertJsonMissing(['token' => $invitation->token])
            ->assertDontSee('obca@firma.pl');
    }

    public function test_employer_invites_recruiter(): void
    {
        Notification::fake();
        Sanctum::actingAs($this->employer());

        $this->postJson('/api/v1/employer/team/invitations', ['email' => 'nowa@firma.pl'])
            ->assertCreated()
            ->assertJsonPath('data.email', 'nowa@firma.pl')
            ->assertJsonMissingPath('data.token');

        Notification::assertSentOnDemand(CompanyTeamInvitation::class);
    }

    public function test_invitation_validation(): void
    {
        $employer = $this->employer();
        CompanyInvitation::factory()->for($employer->company)->create(['email' => 'nowa@firma.pl']);
        Sanctum::actingAs($employer);

        $this->postJson('/api/v1/employer/team/invitations', ['email' => 'nie-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->postJson('/api/v1/employer/team/invitations', ['email' => 'nowa@firma.pl'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'Zaproszenie na ten adres już czeka na akceptację.']);
    }

    public function test_employer_revokes_own_pending_invitation_only(): void
    {
        $employer = $this->employer();
        $invitation = CompanyInvitation::factory()->for($employer->company)->create();
        $foreign = CompanyInvitation::factory()->create();
        Sanctum::actingAs($employer);

        $this->deleteJson("/api/v1/employer/team/invitations/{$foreign->id}")->assertForbidden();
        $this->deleteJson("/api/v1/employer/team/invitations/{$invitation->id}")->assertNoContent();

        $this->assertModelMissing($invitation);
        $this->assertModelExists($foreign);
    }

    public function test_removed_member_loses_company_and_api_tokens(): void
    {
        $employer = $this->employer();
        $colleague = $this->employer($employer->company);
        $colleagueToken = $colleague->createToken('android')->plainTextToken;

        $this->withToken($employer->createToken('iphone')->plainTextToken)
            ->deleteJson("/api/v1/employer/team/members/{$colleague->id}")
            ->assertNoContent();

        $this->assertNull($colleague->refresh()->company_id);
        $this->assertSame(0, $colleague->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->withToken($colleagueToken)->getJson('/api/v1/employer/team')->assertUnauthorized();
    }

    public function test_member_rules_and_cross_company_access(): void
    {
        $employer = $this->employer();
        $stranger = $this->employer();
        Sanctum::actingAs($employer);

        $this->deleteJson("/api/v1/employer/team/members/{$employer->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Nie możesz usunąć z zespołu własnego konta.');
        $this->deleteJson("/api/v1/employer/team/members/{$stranger->id}")->assertForbidden();

        $this->assertNotNull($employer->refresh()->company_id);
        $this->assertNotNull($stranger->refresh()->company_id);
    }

    public function test_only_employers_with_a_company_can_manage_a_team(): void
    {
        $this->getJson('/api/v1/employer/team')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/employer/team')->assertForbidden();

        Sanctum::actingAs(User::factory()->employer()->create(['company_id' => null]));
        $this->getJson('/api/v1/employer/team')->assertForbidden();
        $this->postJson('/api/v1/employer/team/invitations', ['email' => 'nowa@firma.pl'])->assertForbidden();
    }
}
