<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\CompanyVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Employer\InteractsWithEmployerFixtures;
use Tests\TestCase;

class CompanyVerificationTest extends TestCase
{
    use InteractsWithEmployerFixtures, RefreshDatabase;

    public function test_only_admins_can_open_the_companies_list_and_change_verification(): void
    {
        $company = Company::factory()->create();

        $this->get(route('admin.companies.index'))->assertRedirect(route('login'));

        foreach ([User::factory()->create(), $this->employer($company)] as $user) {
            $this->actingAs($user)->get(route('admin.companies.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.companies.verification.store', $company))->assertForbidden();
            $this->actingAs($user)->delete(route('admin.companies.verification.destroy', $company))->assertForbidden();
        }

        $this->assertFalse($company->fresh()->isVerified());
    }

    public function test_admin_lists_companies_and_filters_the_unverified_ones(): void
    {
        $verified = Company::factory()->verified()->create(['name' => 'Alfa', 'nip' => '7781342500']);
        $waiting = Company::factory()->create(['name' => 'Beta']);
        $this->employer($waiting);
        $this->publishedOffer($waiting, []);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.companies.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/companies/Index')
                ->where('status', 'all')
                ->where('counts', ['all' => 2, 'unverified' => 1])
                ->where('companies.0.id', $waiting->id)
                ->where('companies.0.verified', false)
                ->where('companies.0.members_count', 1)
                ->where('companies.0.offers_count', 1)
                ->where('companies.1.id', $verified->id)
                ->where('companies.1.nip', '7781342500')
                ->where('companies.1.verified', true));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.companies.index', ['status' => 'unverified']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('status', 'unverified')
                ->has('companies', 1)
                ->where('companies.0.id', $waiting->id));
    }

    public function test_admin_verifies_a_company_and_its_members_are_notified(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $company = Company::factory()->create();
        $members = [$this->employer($company), $this->employer($company)];
        $outsider = $this->employer();

        $this->actingAs($admin)
            ->post(route('admin.companies.verification.store', $company))
            ->assertRedirect();

        $company->refresh();
        $this->assertTrue($company->isVerified());
        $this->assertSame($admin->id, $company->verified_by_user_id);
        Notification::assertSentTo($members, CompanyVerified::class, function (CompanyVerified $notification, array $channels) use ($members): bool {
            return $channels === ['database']
                && $notification->toArray($members[0])['title'] === 'Twoja firma została zweryfikowana';
        });
        Notification::assertNotSentTo($outsider, CompanyVerified::class);

        $this->actingAs($admin)->post(route('admin.companies.verification.store', $company))->assertRedirect();
        Notification::assertSentToTimes($members[0], CompanyVerified::class, 1);
    }

    public function test_admin_revokes_the_verification(): void
    {
        $company = Company::factory()->verified()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.companies.verification.destroy', $company))
            ->assertRedirect();

        $company->refresh();
        $this->assertFalse($company->isVerified());
        $this->assertNull($company->verified_by_user_id);
    }

    public function test_dashboard_counts_unverified_companies(): void
    {
        Company::factory()->count(2)->create();
        Company::factory()->verified()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('stats.unverified_companies', 2));
    }

    public function test_employer_offers_page_knows_whether_the_company_is_verified(): void
    {
        $company = Company::factory()->create();
        $employer = $this->employer($company);

        $this->actingAs($employer)
            ->get(route('employer.offers.index'))
            ->assertInertia(fn (Assert $page) => $page->where('companyVerified', false));

        $company->forceFill(['verified_at' => now()])->save();
        $employer->unsetRelation('company');

        $this->actingAs($employer)
            ->get(route('employer.offers.index'))
            ->assertInertia(fn (Assert $page) => $page->where('companyVerified', true));
    }

    public function test_candidate_sees_the_badge_and_can_filter_verified_companies(): void
    {
        $skill = $this->skill('Księgowość');
        $candidate = $this->candidate([$skill]);
        $verifiedOffer = $this->publishedOffer(Company::factory()->verified()->create(), [$skill]);
        $otherOffer = $this->publishedOffer(Company::factory()->create(), [$skill]);

        $this->actingAs($candidate->user)
            ->get(route('candidate.offers.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('offers', 2)
                ->where('filters.verified_only', false));

        $this->actingAs($candidate->user)
            ->get(route('candidate.offers.index', ['verified_only' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('offers', 1)
                ->where('offers.0.id', $verifiedOffer->id)
                ->where('offers.0.company.verified', true)
                ->where('filters.verified_only', true));

        $this->actingAs($candidate->user)
            ->get(route('candidate.offers.show', $otherOffer))
            ->assertInertia(fn (Assert $page) => $page->where('offer.company.verified', false));
    }

    public function test_public_pages_show_the_badge_and_filter_verified_companies(): void
    {
        $verified = Company::factory()->verified()->create();
        $verifiedOffer = $this->publishedOffer($verified, []);
        $this->publishedOffer(Company::factory()->create(), []);

        $this->get(route('public.offers.index', ['verified_only' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('offers.data', 1)
                ->where('offers.data.0.id', $verifiedOffer->id)
                ->where('offers.data.0.company.verified', true)
                ->where('filters.verified_only', true));

        $this->get(route('public.companies.show', $verified))
            ->assertInertia(fn (Assert $page) => $page->where('company.verified', true));
    }

    public function test_candidate_invitations_and_conversation_header_carry_the_badge(): void
    {
        $candidate = $this->candidate([]);
        $offer = $this->publishedOffer(Company::factory()->verified()->create(), []);
        $invitation = Invitation::factory()->for($candidate)->for($offer)->accepted()->create();
        $conversation = Conversation::factory()->for($invitation)->create();

        $this->actingAs($candidate->user)
            ->get(route('candidate.invitations.index'))
            ->assertInertia(fn (Assert $page) => $page->where('invitations.0.company.verified', true));

        $this->actingAs($candidate->user)
            ->get(route('conversations.show', $conversation))
            ->assertInertia(fn (Assert $page) => $page->where('conversation.counterpart.verified', true));
    }
}
