<?php

namespace Tests\Feature\Reviews;

use App\Enums\ReviewStatus;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Models\Conversation;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompanyReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $conversation = Conversation::factory()->create();
        $this->candidate = $conversation->invitation->candidateProfile->user;
        $this->company = $conversation->invitation->jobOffer->company;
    }

    /**
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

    public function test_index_lists_companies_from_accepted_invitations_without_a_review(): void
    {
        $reviewedConversation = Conversation::factory()->create([
            'invitation_id' => Invitation::factory()->accepted()->create([
                'candidate_profile_id' => $this->candidate->candidateProfile->id,
            ]),
        ]);
        CompanyReview::factory()->create([
            'company_id' => $reviewedConversation->invitation->jobOffer->company_id,
            'user_id' => $this->candidate->id,
            'status' => ReviewStatus::Pending,
        ]);
        Invitation::factory()->create(['candidate_profile_id' => $this->candidate->candidateProfile->id]);

        $this->actingAs($this->candidate)
            ->get(route('reviews.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reviews/Index')
                ->has('reviewableCompanies', 1)
                ->where('reviewableCompanies.0.id', $this->company->id)
                ->has('reviews', 1)
                ->where('reviews.0.status', 'pending')
                ->where('reviews.0.can_edit', true));
    }

    public function test_candidate_with_accepted_invitation_submits_a_pending_review(): void
    {
        $this->actingAs($this->candidate)
            ->post(route('reviews.store', $this->company), $this->validPayload())
            ->assertRedirect(route('reviews.index'));

        $review = CompanyReview::sole();
        $this->assertSame($this->candidate->id, $review->user_id);
        $this->assertSame($this->company->id, $review->company_id);
        $this->assertSame(ReviewStatus::Pending, $review->status);
        $this->assertCount(0, $this->company->approvedReviews()->get());
    }

    public function test_candidate_without_accepted_invitation_cannot_review_the_company(): void
    {
        $pendingInvitation = Invitation::factory()->create(['candidate_profile_id' => $this->candidate->candidateProfile->id]);
        $invitedCompany = $pendingInvitation->jobOffer->company;

        $this->actingAs($this->candidate)->get(route('reviews.create', $invitedCompany))->assertForbidden();
        $this->actingAs($this->candidate)
            ->post(route('reviews.store', $invitedCompany), $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('company_reviews', 0);
    }

    public function test_employers_cannot_submit_reviews(): void
    {
        $employer = User::factory()->employer()->create();

        $this->actingAs($employer)
            ->post(route('reviews.store', $this->company), $this->validPayload())
            ->assertForbidden();
    }

    public function test_candidate_can_review_a_company_only_once(): void
    {
        CompanyReview::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->candidate->id,
            'status' => ReviewStatus::Approved,
        ]);

        $this->actingAs($this->candidate)
            ->post(route('reviews.store', $this->company), $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('company_reviews', 1);
    }

    public function test_create_page_redirects_to_editing_an_existing_pending_review(): void
    {
        $review = CompanyReview::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->candidate->id,
            'status' => ReviewStatus::Pending,
        ]);

        $this->actingAs($this->candidate)
            ->get(route('reviews.create', $this->company))
            ->assertRedirect(route('reviews.edit', $review));
    }

    public function test_author_can_edit_only_her_pending_review(): void
    {
        $review = CompanyReview::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->candidate->id,
            'status' => ReviewStatus::Pending,
        ]);

        $this->actingAs($this->candidate)
            ->put(route('reviews.update', $review), $this->validPayload(['rating_return' => 2, 'quote' => 'Zmieniłam zdanie po czasie.']))
            ->assertRedirect(route('reviews.index'));

        $review->refresh();
        $this->assertSame(2, $review->rating_return);
        $this->assertSame(ReviewStatus::Pending, $review->status);

        $otherCandidate = User::factory()->create();
        $this->actingAs($otherCandidate)->get(route('reviews.edit', $review))->assertForbidden();

        $review->update(['status' => ReviewStatus::Approved]);
        $this->actingAs($this->candidate)
            ->put(route('reviews.update', $review), $this->validPayload())
            ->assertForbidden();
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'rating below range' => [['rating_return' => 0], 'rating_return'],
            'rating above range' => [['rating_flexibility' => 6], 'rating_flexibility'],
            'missing rating' => [['rating_no_pregnancy_questions' => null], 'rating_no_pregnancy_questions'],
            'quote too long' => [['quote' => str_repeat('a', 301)], 'quote'],
            'email in quote' => [['quote' => 'Piszcie śmiało: anna.kowalska@example.com'], 'quote'],
            'phone in quote' => [['quote' => 'Zadzwońcie do mnie: 601 234 567, opowiem więcej.'], 'quote'],
            'phone with prefix in quote' => [['quote' => 'Kontakt +48 601-234-567 w sprawie pracy.'], 'quote'],
            'email in author label' => [['author_label' => 'ania@example.com'], 'author_label'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_reviews_are_rejected(array $overrides, string $field): void
    {
        $this->actingAs($this->candidate)
            ->post(route('reviews.store', $this->company), $this->validPayload($overrides))
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('company_reviews', 0);
    }

    public function test_quote_with_numbers_that_are_not_phone_numbers_is_accepted(): void
    {
        $this->actingAs($this->candidate)
            ->post(route('reviews.store', $this->company), $this->validPayload([
                'quote' => 'Od 2026 r. pracuję 3/5 etatu, spotkania do 15:00.',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('company_reviews', 1);
    }
}
