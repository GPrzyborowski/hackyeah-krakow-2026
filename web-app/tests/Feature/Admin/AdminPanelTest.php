<?php

namespace Tests\Feature\Admin;

use App\Enums\ReviewStatus;
use App\Models\Article;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Models\LegalSource;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_dashboard_shows_platform_counts(): void
    {
        User::factory()->count(2)->create();
        User::factory()->employer()->create();
        JobOffer::factory()->published()->create();
        Invitation::factory()->accepted()->create();
        CompanyReview::factory()->create(['status' => ReviewStatus::Pending]);
        CompanyReview::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Dashboard')
                ->where('stats.admins', 1)
                ->where('stats.pending_reviews', 1)
                ->where('stats.accepted_invitations', 1)
                ->where('stats.published_offers', 1)
                ->where('stats.job_share_pairs', 0)
                ->has('stats.candidates')
                ->has('stats.employers'));
    }

    public function test_dashboard_shows_candidate_counts_per_stage_without_personal_data(): void
    {
        CandidateProfile::factory()->count(2)->pregnant()->create();
        CandidateProfile::factory()->count(3)->afterLeave()->create();
        CandidateProfile::factory()->create(['stage' => null]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.candidates_pregnant', 2)
                ->where('stats.candidates_after_leave', 3)
                ->where('stats.candidates_without_stage', 1));

        foreach (CandidateProfile::query()->with('user')->get() as $profile) {
            $response->assertDontSee($profile->user->email);
        }
    }

    public function test_dashboard_shows_content_and_newsletter_counts(): void
    {
        Article::factory()->count(2)->create();
        LegalSource::factory()->count(3)->create();
        NewsletterSubscriber::factory()->confirmed()->count(2)->create();
        NewsletterSubscriber::factory()->create();
        NewsletterSubscriber::factory()->unsubscribed()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.articles', Article::query()->count())
                ->where('stats.legal_sources', LegalSource::query()->count())
                ->where('stats.newsletter_subscribers', 2));
    }

    public function test_non_admins_cannot_access_the_admin_panel(): void
    {
        $review = CompanyReview::factory()->create(['status' => ReviewStatus::Pending]);

        foreach ([User::factory()->create(), User::factory()->employer()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.reviews.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.reviews.approve', $review))->assertForbidden();
            $this->actingAs($user)->post(route('admin.reviews.reject', $review))->assertForbidden();
        }

        $this->assertSame(ReviewStatus::Pending, $review->refresh()->status);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('admin.reviews.index'))->assertRedirect(route('login'));
    }

    public function test_reviews_are_listed_by_status_tab(): void
    {
        $pending = CompanyReview::factory()->create(['status' => ReviewStatus::Pending]);
        CompanyReview::factory()->create(['status' => ReviewStatus::Rejected]);
        CompanyReview::factory()->create(['status' => ReviewStatus::Rejected]);

        $this->actingAs($this->admin)
            ->get(route('admin.reviews.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/reviews/Index')
                ->where('status', 'pending')
                ->where('counts.pending', 1)
                ->where('counts.rejected', 2)
                ->has('reviews', 1)
                ->where('reviews.0.id', $pending->id));

        $this->actingAs($this->admin)
            ->get(route('admin.reviews.index', ['status' => 'rejected']))
            ->assertInertia(fn (Assert $page) => $page->where('status', 'rejected')->has('reviews', 2));
    }

    public function test_approving_a_review_publishes_it_on_the_company_page(): void
    {
        $company = Company::factory()->create();
        $review = CompanyReview::factory()->for($company)->create(['status' => ReviewStatus::Pending]);

        $this->actingAs($this->admin)
            ->from(route('admin.reviews.index'))
            ->post(route('admin.reviews.approve', $review))
            ->assertRedirect(route('admin.reviews.index'));

        $this->assertSame(ReviewStatus::Approved, $review->refresh()->status);
        $this->assertTrue($company->approvedReviews()->whereKey($review->id)->exists());

        $this->get(route('public.companies.show', $company))
            ->assertInertia(fn (Assert $page) => $page->has('reviews', 1)->where('reviews.0.id', $review->id));
    }

    public function test_rejected_review_is_not_shown_on_the_company_page(): void
    {
        $company = Company::factory()->create();
        $review = CompanyReview::factory()->for($company)->create(['status' => ReviewStatus::Pending]);

        $this->actingAs($this->admin)->post(route('admin.reviews.reject', $review))->assertRedirect();

        $this->assertSame(ReviewStatus::Rejected, $review->refresh()->status);
        $this->assertNull($company->averageRating());

        $this->get(route('public.companies.show', $company))
            ->assertInertia(fn (Assert $page) => $page->has('reviews', 0)->where('company.rating.count', 0));
    }
}
