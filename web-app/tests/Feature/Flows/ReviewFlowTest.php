<?php

namespace Tests\Feature\Flows;

use App\Enums\ReviewStatus;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReviewFlowTest extends TestCase
{
    use InteractsWithFlows, RefreshDatabase;

    public function test_candidate_reviews_company_after_accepting_its_invitation_and_review_appears_after_approval(): void
    {
        Notification::fake();
        $company = Company::factory()->create(['name' => 'Zielone Biuro']);
        $offer = JobOffer::factory()->published()->for($company)->create();
        $candidate = CandidateProfile::factory()->published()->for(User::factory()->state(['name' => 'Marta Zawadzka']))->create();
        $invitation = Invitation::factory()->for($offer)->create(['candidate_profile_id' => $candidate->id]);
        $admin = User::factory()->admin()->create();
        $review = [
            'rating_return' => 5,
            'rating_flexibility' => 4,
            'rating_no_pregnancy_questions' => 5,
            'quote' => 'Rozmowa dotyczyła wyłącznie kompetencji, a godziny pracy są naprawdę elastyczne.',
            'author_label' => 'Mama dwulatka, HR',
        ];

        $this->actingAs($candidate->user)->get(route('reviews.create', $company))->assertForbidden();
        $this->actingAs($candidate->user)->post(route('reviews.store', $company), $review)->assertForbidden();

        $this->actingAs($candidate->user)->post(route('candidate.invitations.accept', $invitation))->assertRedirect();

        $this->actingAs($candidate->user)
            ->get(route('reviews.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('reviews/Index')
                ->has('reviewableCompanies', 1)
                ->where('reviewableCompanies.0.id', $company->id));

        $this->actingAs($candidate->user)
            ->post(route('reviews.store', $company), [...$review, 'quote' => 'Piszcie do mnie: marta@example.test, chętnie opowiem.'])
            ->assertSessionHasErrors('quote');
        $this->actingAs($candidate->user)
            ->post(route('reviews.store', $company), $review)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('reviews.index'));

        $stored = CompanyReview::query()->sole();
        $this->assertSame(ReviewStatus::Pending, $stored->status);
        $this->assertTrue($stored->author->is($candidate->user));
        $this->actingAs($candidate->user)->post(route('reviews.store', $company), $review)->assertForbidden();

        $this->get(route('public.companies.show', $company))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('public/companies/Show')->has('reviews', 0));

        $this->actingAs($candidate->user)->post(route('admin.reviews.approve', $stored))->assertForbidden();
        $this->assertSame(ReviewStatus::Pending, $stored->refresh()->status);

        $this->actingAs($admin)
            ->get(route('admin.reviews.index'))
            ->assertInertia(fn (Assert $page) => $page->has('reviews', 1)->where('reviews.0.id', $stored->id));
        $this->actingAs($admin)->post(route('admin.reviews.approve', $stored))->assertRedirect();
        $this->assertSame(ReviewStatus::Approved, $stored->refresh()->status);

        $this->actingAs($candidate->user)->post(route('reviews.update', $stored), [...$review, '_method' => 'PUT'])->assertForbidden();

        auth()->logout();
        $companyPage = $this->get(route('public.companies.show', $company));
        $companyPage->assertInertia(fn (Assert $page) => $page
            ->has('reviews', 1)
            ->where('reviews.0.quote', $review['quote'])
            ->where('reviews.0.author_label', 'Mama dwulatka, HR')
            ->where('reviews.0.rating_no_pregnancy_questions', 5));
        $this->assertStringNotContainsString('Zawadzka', $this->pagePropsJson($companyPage));
        $this->assertStringNotContainsString($candidate->user->email, $this->pagePropsJson($companyPage));
    }
}
