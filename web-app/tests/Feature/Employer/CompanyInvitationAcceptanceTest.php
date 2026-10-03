<?php

namespace Tests\Feature\Employer;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyInvitationAcceptanceTest extends TestCase
{
    use InteractsWithEmployerFixtures, RefreshDatabase;

    public function test_guest_sees_registration_form_for_a_valid_link(): void
    {
        $invitation = CompanyInvitation::factory()->create(['email' => 'nowa@firma.pl']);

        $this->get($this->link($invitation))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/AcceptCompanyInvitation')
                ->where('state', 'register')
                ->where('invitation.email', 'nowa@firma.pl')
                ->where('invitation.company_name', $invitation->company->name));
    }

    public function test_link_without_valid_signature_is_rejected(): void
    {
        $invitation = CompanyInvitation::factory()->create();

        $this->get(route('company-invitations.show', $invitation->token))->assertForbidden();
    }

    public function test_guest_registers_verified_employer_attached_to_the_company(): void
    {
        $invitation = CompanyInvitation::factory()->create(['email' => 'nowa@firma.pl']);

        $this->post(route('company-invitations.register', $invitation->token), [
            'name' => 'Nowa Rekruterka',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('employer.offers.index'));

        $user = User::where('email', 'nowa@firma.pl')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserRole::Employer, $user->role);
        $this->assertSame($invitation->company_id, $user->company_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($invitation->refresh()->accepted_at);
        $this->get(route('employer.offers.index'))->assertOk();
    }

    public function test_logged_in_employer_without_company_joins_the_team(): void
    {
        $invitation = CompanyInvitation::factory()->create(['email' => 'byla@firma.pl']);
        $user = User::factory()->employer()->unverified()->create(['email' => 'Byla@Firma.pl', 'company_id' => null]);

        $this->actingAs($user)
            ->get($this->link($invitation))
            ->assertInertia(fn (Assert $page) => $page->where('state', 'accept'));

        $this->actingAs($user)
            ->post(route('company-invitations.accept', $invitation->token))
            ->assertRedirect(route('employer.offers.index'));

        $user->refresh();
        $this->assertSame($invitation->company_id, $user->company_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($invitation->refresh()->accepted_at);
    }

    public function test_guest_whose_address_has_an_account_is_asked_to_log_in(): void
    {
        $invitation = CompanyInvitation::factory()->create(['email' => 'byla@firma.pl']);
        User::factory()->employer()->create(['email' => 'byla@firma.pl', 'company_id' => null]);

        $this->get($this->link($invitation))
            ->assertInertia(fn (Assert $page) => $page->where('state', 'login'))
            ->assertSessionHas('url.intended');

        $this->post(route('company-invitations.register', $invitation->token), [
            'name' => 'Podróbka',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('name');

        $this->assertSame(1, User::where('email', 'byla@firma.pl')->count());
    }

    public function test_expired_and_used_links_show_a_friendly_error_and_cannot_be_used(): void
    {
        $expired = CompanyInvitation::factory()->expired()->create();
        $used = CompanyInvitation::factory()->accepted()->create();

        $this->get($this->link($expired))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('state', 'invalid')
                ->where('problem', fn (string $problem): bool => str_contains($problem, 'wygasło')));

        $this->get($this->link($used))
            ->assertInertia(fn (Assert $page) => $page
                ->where('state', 'invalid')
                ->where('problem', fn (string $problem): bool => str_contains($problem, 'wykorzystane')));

        $this->post(route('company-invitations.register', $expired->token), [
            'name' => 'Spóźniona',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('invitation');

        $this->assertDatabaseMissing('users', ['email' => $expired->email]);
    }

    public function test_accounts_that_cannot_join_get_an_explanation_instead_of_being_attached(): void
    {
        $invitation = CompanyInvitation::factory()->create(['email' => 'kandydatka@example.com']);
        $candidate = User::factory()->create(['email' => 'kandydatka@example.com']);
        $otherRecruiter = $this->employer(Company::factory()->create());

        $this->actingAs($candidate)
            ->get($this->link($invitation))
            ->assertInertia(fn (Assert $page) => $page->where('state', 'invalid'));
        $this->actingAs($candidate)
            ->post(route('company-invitations.accept', $invitation->token))
            ->assertSessionHasErrors('invitation');

        $this->actingAs($otherRecruiter)
            ->get($this->link($invitation))
            ->assertInertia(fn (Assert $page) => $page
                ->where('state', 'invalid')
                ->where('problem', fn (string $problem): bool => str_contains($problem, 'inny adres')));

        $this->assertNull($candidate->refresh()->company_id);
        $this->assertNull($invitation->refresh()->accepted_at);
    }

    public function test_registration_with_existing_nip_points_to_team_invitation(): void
    {
        Company::factory()->create(['nip' => '1234563218']);

        $this->post(route('register.store'), [
            'role' => 'employer',
            'company_name' => 'Kopia',
            'company_nip' => '1234563218',
            'name' => 'Ktoś',
            'email' => 'ktos@firma.pl',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors(['company_nip' => 'Firma z tym NIP-em ma już konto w mumjobs. Poproś osobę z Twojej firmy o zaproszenie do zespołu w mumjobs.']);
    }

    private function link(CompanyInvitation $invitation): string
    {
        return URL::signedRoute('company-invitations.show', ['token' => $invitation->token]);
    }
}
