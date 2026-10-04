<?php

namespace Tests\Feature\Api\Employer;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Employer\BuildsEmployerDashboardDataset;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use BuildsEmployerDashboardDataset, RefreshDatabase;

    public function test_employer_gets_dashboard_json(): void
    {
        ['employer' => $employer, 'recruiterOffer' => $recruiterOffer, 'payrollOffer' => $payrollOffer] = $this->dashboardDataset();
        Sanctum::actingAs($employer);

        $this->getJson('/api/v1/employer/dashboard')
            ->assertOk()
            ->assertJsonPath('data.greeting.first_name', 'Rekruterka')
            ->assertJsonPath('data.company.name', 'Zielone Biuro')
            ->assertJsonPath('data.company.verified', false)
            ->assertJsonPath('data.stats.published_offers_count', 2)
            ->assertJsonPath('data.stats.matching_candidates_count', 3)
            ->assertJsonPath('data.stats.to_review_count', 2)
            ->assertJsonPath('data.stats.invitations_sent_recent_count', 1)
            ->assertJsonPath('data.stats.acceptance_rate', 50)
            ->assertJsonPath('data.stats.active_conversations_count', 1)
            ->assertJsonPath('data.stats.submitted_pairs_count', 1)
            ->assertJsonPath('data.stats.parent_friendly.count', 1)
            ->assertJsonPath('data.stats.parent_friendly.total', 2)
            ->assertJsonPath('data.funnel.0.offer_id', $payrollOffer->id)
            ->assertJsonPath('data.funnel.1.offer_id', $recruiterOffer->id)
            ->assertJsonPath('data.funnel.1.accepted_count', 1)
            ->assertJsonPath('data.todo.0.kind', 'unread_messages')
            ->assertJsonPath('data.reviews.approved_count', 1)
            ->assertJsonCount(3, 'data.activity')
            ->assertJsonStructure(['data' => [
                'stats' => ['recent_days', 'responded_count', 'accepted_count'],
                'funnel' => [['title', 'matched_count', 'reviewed_count', 'invited_count', 'accepted_count', 'to_review_count', 'is_parent_friendly', 'is_job_share']],
                'todo' => [['kind', 'count', 'offer_id', 'offer_title', 'names', 'hints', 'url']],
                'activity' => [['id', 'kind', 'title', 'body', 'url', 'read', 'created_at', 'target']],
            ]])
            ->assertDontSee('Ukryta')
            ->assertDontSee('Barbara Lis');
    }

    public function test_guests_get_401(): void
    {
        $this->getJson('/api/v1/employer/dashboard')->assertUnauthorized();
    }

    public function test_candidates_unverified_employers_and_employers_without_company_get_403(): void
    {
        $this->requireEmployerEmailVerification();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/employer/dashboard')->assertForbidden();

        Sanctum::actingAs(User::factory()->employer(Company::factory()->create())->unverified()->create());
        $this->getJson('/api/v1/employer/dashboard')->assertForbidden();

        Sanctum::actingAs(User::factory()->employer()->create(['company_id' => null]));
        $this->getJson('/api/v1/employer/dashboard')->assertForbidden();
    }
}
