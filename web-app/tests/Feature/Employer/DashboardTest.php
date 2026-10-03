<?php

namespace Tests\Feature\Employer;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use BuildsEmployerDashboardDataset, RefreshDatabase;

    public function test_dashboard_shows_company_kpis_funnel_todo_and_activity(): void
    {
        ['employer' => $employer, 'recruiterOffer' => $recruiterOffer, 'payrollOffer' => $payrollOffer] = $this->dashboardDataset();
        $employer->company->forceFill(['verified_at' => now()])->save();

        $this->actingAs($employer)
            ->get(route('employer.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employer/Dashboard')
                ->where('greeting.first_name', 'Rekruterka')
                ->where('company.name', 'Zielone Biuro')
                ->where('company.verified', true)
                ->where('stats.published_offers_count', 2)
                ->where('stats.matching_candidates_count', 3)
                ->where('stats.to_review_count', 2)
                ->where('stats.invitations_sent_recent_count', 1)
                ->where('stats.responded_count', 2)
                ->where('stats.accepted_count', 1)
                ->where('stats.acceptance_rate', 50)
                ->where('stats.active_conversations_count', 1)
                ->where('stats.submitted_pairs_count', 1)
                ->where('stats.parent_friendly', ['count' => 1, 'total' => 2])
                ->has('funnel', 2)
                ->where('funnel.0.offer_id', $payrollOffer->id)
                ->where('funnel.0.matched_count', 2)
                ->where('funnel.0.to_review_count', 2)
                ->where('funnel.0.is_parent_friendly', false)
                ->where('funnel.1.offer_id', $recruiterOffer->id)
                ->where('funnel.1.matched_count', 2)
                ->where('funnel.1.reviewed_count', 3)
                ->where('funnel.1.to_review_count', 0)
                ->where('funnel.1.invited_count', 2)
                ->where('funnel.1.responded_count', 2)
                ->where('funnel.1.accepted_count', 1)
                ->where('funnel.1.submitted_pairs_count', 1)
                ->where('funnel.1.is_parent_friendly', true)
                ->where('todo', fn ($todo): bool => collect($todo)->map(fn (array $item): string => $item['kind'].':'.$item['count'])->all() === [
                    'unread_messages:1',
                    'submitted_pairs:1',
                    'candidates_to_review:2',
                    'offer_incomplete:3',
                ])
                ->where('todo.0.names', ['Anna Nowak'])
                ->where('todo.3.offer_id', $payrollOffer->id)
                ->where('todo.3.hints', ['missing_required_skills', 'missing_salary', 'no_flexible_hours'])
                ->where('reviews.approved_count', 1)
                ->has('activity', 3)
                ->where('activity.0.kind', 'pair_submitted')
                ->where('activity', fn ($activity): bool => collect($activity)->pluck('kind')->sort()->values()->all() === ['invitation_accepted', 'new_message', 'pair_submitted']));
    }

    public function test_new_company_gets_empty_dashboard_with_review_hint(): void
    {
        $employer = $this->employer();

        $this->actingAs($employer)
            ->get(route('employer.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('company.verified', false)
                ->where('company.verified_at', null)
                ->where('stats.matching_candidates_count', 0)
                ->where('stats.acceptance_rate', null)
                ->where('stats.parent_friendly', ['count' => 0, 'total' => 0])
                ->has('funnel', 0)
                ->has('activity', 0)
                ->where('todo.0.kind', 'no_approved_reviews'));
    }

    public function test_dashboard_query_count_does_not_grow_with_offers_and_candidates(): void
    {
        $employer = $this->employer();
        $skill = $this->skill('Rekrutacja IT');
        $this->publishedOffer($employer->company, [$skill]);
        $this->candidate([$skill]);

        $smallQueries = $this->countQueries(fn () => $this->actingAs($employer->fresh())->get(route('employer.dashboard'))->assertOk());

        foreach (range(1, 8) as $index) {
            $offer = $this->publishedOffer($employer->company, [$skill, $this->skill("Umiejętność {$index}")]);
            $candidate = $this->candidate([$skill], name: "Kandydatka{$index} Nowak");
            $offer->decisions()->create(['candidate_profile_id' => $candidate->id, 'decision' => 'saved']);
        }

        $largeQueries = $this->countQueries(fn () => $this->actingAs($employer->fresh())->get(route('employer.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('funnel', 9)->where('stats.matching_candidates_count', 9)));

        $this->assertSame($smallQueries, $largeQueries);
        $this->assertLessThanOrEqual(25, $largeQueries);
    }

    public function test_dashboard_redirect_and_access(): void
    {
        $employer = $this->employer();

        $this->actingAs($employer)->get(route('dashboard'))->assertRedirect('/employer');
        $this->actingAs(User::factory()->create())->get('/employer')->assertForbidden();
        $this->actingAs(User::factory()->employer(Company::factory()->create())->unverified()->create())
            ->get('/employer')
            ->assertRedirect(route('verification.notice'));
        $this->actingAs(User::factory()->employer()->create(['company_id' => null]))->get('/employer')->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/employer')->assertRedirect(route('login'));
    }

    private function countQueries(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
