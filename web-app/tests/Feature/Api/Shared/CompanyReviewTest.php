<?php

namespace Tests\Feature\Api\Shared;

use App\Enums\ReviewStatus;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $conversation = Conversation::factory()->create();
        $this->candidate = $conversation->invitation->candidateProfile->user;
        $this->company = $conversation->invitation->jobOffer->company;
    }

    public function test_index_lists_reviewable_companies_and_own_reviews(): void
    {
        $reviewed = Conversation::factory()->create();
        $reviewed->invitation->update(['candidate_profile_id' => $this->candidate->candidateProfile->id]);
        $review = CompanyReview::factory()->create([
            'company_id' => $reviewed->invitation->jobOffer->company_id,
            'user_id' => $this->candidate->id,
            'status' => ReviewStatus::Pending,
        ]);
        CompanyReview::factory()->create(['user_id' => User::factory()->create()->id]);
        Sanctum::actingAs($this->candidate);

        $this->getJson('/api/v1/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data.reviewable_companies')
            ->assertJsonPath('data.reviewable_companies.0.id', $this->company->id)
            ->assertJsonCount(1, 'data.reviews')
            ->assertJsonPath('data.reviews.0.id', $review->id)
            ->assertJsonPath('data.reviews.0.status', 'pending')
            ->assertJsonPath('data.reviews.0.can_edit', true);
    }

    public function test_candidate_reviews_a_company_she_talked_to_and_edits_it_while_pending(): void
    {
        Sanctum::actingAs($this->candidate);

        $reviewId = $this->postJson("/api/v1/reviews/companies/{$this->company->id}", $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.status_label', 'Czeka na moderację')
            ->json('data.id');

        $this->postJson("/api/v1/reviews/companies/{$this->company->id}", $this->validPayload())->assertForbidden();

        $this->putJson("/api/v1/reviews/{$reviewId}", $this->validPayload(['quote' => 'Elastyczny grafik od pierwszego dnia.']))
            ->assertOk()
            ->assertJsonPath('data.quote', 'Elastyczny grafik od pierwszego dnia.');
    }

    public function test_validation_includes_the_personal_data_rule(): void
    {
        Sanctum::actingAs($this->candidate);

        $this->postJson("/api/v1/reviews/companies/{$this->company->id}", $this->validPayload([
            'quote' => 'Piszcie śmiało: marta@example.com',
            'rating_return' => 6,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['quote', 'rating_return']);

        $this->assertDatabaseCount('company_reviews', 0);
    }

    public function test_cannot_review_unknown_company_or_edit_others_or_approved_reviews(): void
    {
        $approved = CompanyReview::factory()->for($this->company)->create(['user_id' => $this->candidate->id, 'status' => ReviewStatus::Approved]);
        $foreign = CompanyReview::factory()->create(['user_id' => User::factory()->create()->id, 'status' => ReviewStatus::Pending]);
        Sanctum::actingAs($this->candidate);

        $this->postJson('/api/v1/reviews/companies/'.Company::factory()->create()->id, $this->validPayload())->assertForbidden();
        $this->putJson("/api/v1/reviews/{$approved->id}", $this->validPayload())->assertForbidden();
        $this->putJson("/api/v1/reviews/{$foreign->id}", $this->validPayload())->assertForbidden();
    }

    public function test_employers_get_403(): void
    {
        Sanctum::actingAs(User::factory()->employer($this->company)->create());

        $this->getJson('/api/v1/reviews')->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'rating_return' => 5,
            'rating_flexibility' => 4,
            'rating_no_pregnancy_questions' => 5,
            'quote' => 'Po powrocie dostałam miesiąc na wdrożenie.',
            'author_label' => 'Mama dwójki, księgowość',
            ...$overrides,
        ];
    }
}
