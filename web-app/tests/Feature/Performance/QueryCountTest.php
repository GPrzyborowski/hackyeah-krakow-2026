<?php

namespace Tests\Feature\Performance;

use App\Models\Company;
use App\Models\CompanyReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Employer\InteractsWithEmployerFixtures;
use Tests\TestCase;

/**
 * Guards list pages against N+1 regressions: the query count must stay flat as rows grow.
 */
class QueryCountTest extends TestCase
{
    use InteractsWithEmployerFixtures, RefreshDatabase;

    private const int ROWS = 20;

    private const int MAX_QUERIES = 15;

    public function test_candidate_offers_list_does_not_query_per_offer(): void
    {
        $skill = $this->skill('Rekrutacja IT');
        $otherSkill = $this->skill('Onboarding');
        $candidate = $this->candidate([$skill]);

        foreach (range(1, self::ROWS) as $index) {
            $company = Company::factory()->create();
            CompanyReview::factory()->for($company)->create();
            $this->publishedOffer($company, [$skill], [$otherSkill])
                ->update(['salary_min' => 6000, 'salary_max' => 8000, 'flexible_hours' => true]);
        }

        [$response, $queryCount] = $this->countQueries(fn (): TestResponse => $this->actingAs($candidate->user)->get(route('candidate.offers.index')));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('offers', self::ROWS)
            ->where('offers.0.is_parent_friendly', true)
            ->where('offers.0.match.score', 80));
        $this->assertLessThanOrEqual(self::MAX_QUERIES, $queryCount);
    }

    public function test_employer_candidates_page_does_not_query_per_candidate(): void
    {
        $skill = $this->skill('Rekrutacja IT');
        $otherSkill = $this->skill('Onboarding');
        $employer = $this->employer();
        $offer = $this->publishedOffer($employer->company, [$skill, $otherSkill]);
        $this->publishedOffer($employer->company, [$skill]);
        $this->publishedOffer($employer->company, [$otherSkill]);

        foreach (range(1, self::ROWS) as $index) {
            $candidate = $this->candidate([$skill, $otherSkill], name: "Kandydatka{$index} Nowak");

            if ($index <= 5) {
                $offer->decisions()->create(['candidate_profile_id' => $candidate->id, 'decision' => 'saved']);
            }
        }

        [$response, $queryCount] = $this->countQueries(fn (): TestResponse => $this->actingAs($employer)->get("/employer/candidates?offer={$offer->id}"));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('offers', 3)
            ->has('saved', 5)
            ->where('remainingCount', self::ROWS - 5)
            ->where('candidate.match.score', 100));
        $this->assertLessThanOrEqual(self::MAX_QUERIES + 5, $queryCount);
    }

    public function test_employer_offers_list_does_not_query_per_candidate(): void
    {
        $skill = $this->skill('Rekrutacja IT');
        $employer = $this->employer();
        CompanyReview::factory()->for($employer->company)->create();
        $this->publishedOffer($employer->company, [$skill]);

        foreach (range(1, self::ROWS) as $index) {
            $this->candidate([$skill], name: "Kandydatka{$index} Nowak");
        }

        [$response, $queryCount] = $this->countQueries(fn (): TestResponse => $this->actingAs($employer)->get(route('employer.offers.index')));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('offers', 1)
            ->where('offers.0.statistics.matched_count', self::ROWS));
        $this->assertLessThanOrEqual(self::MAX_QUERIES, $queryCount);
    }

    /**
     * @param  callable(): TestResponse  $request
     * @return array{0: TestResponse, 1: int}
     */
    private function countQueries(callable $request): array
    {
        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        return [$request(), $queryCount];
    }
}
