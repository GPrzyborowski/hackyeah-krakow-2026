<?php

namespace Tests\Feature\Employer;

use App\Enums\ReviewStatus;
use App\Models\Company;
use App\Models\CompanyReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use InteractsWithEmployerFixtures, RefreshDatabase;

    public function test_company_page_shows_approved_review_averages()
    {
        $employer = $this->employer();
        CompanyReview::factory()->for($employer->company)->create(['rating_return' => 5, 'rating_flexibility' => 4, 'rating_no_pregnancy_questions' => 3]);
        CompanyReview::factory()->for($employer->company)->create(['rating_return' => 4, 'rating_flexibility' => 4, 'rating_no_pregnancy_questions' => 5]);
        CompanyReview::factory()->for($employer->company)->create(['status' => ReviewStatus::Pending, 'rating_return' => 1]);
        CompanyReview::factory()->create();

        $this->actingAs($employer)
            ->get('/employer/company')
            ->assertInertia(fn (Assert $page) => $page
                ->component('employer/company/Edit')
                ->where('company.id', $employer->company_id)
                ->has('reviews', 2)
                ->where('ratings.count', 2)
                ->where('ratings.return', 4.5)
                ->where('ratings.flexibility', 4)
                ->where('ratings.no_pregnancy_questions', 4)
                ->where('ratings.overall', 4.2));
    }

    public function test_employer_updates_own_company()
    {
        $employer = $this->employer();
        $otherCompany = Company::factory()->create(['name' => 'Inna firma']);

        $this->actingAs($employer)
            ->put('/employer/company', ['name' => 'Zielone Biuro', 'nip' => '123-456-78-90', 'city' => 'Kraków', 'description' => 'Elastyczne godziny.'])
            ->assertRedirect(route('employer.company.edit'));

        $company = $employer->company->refresh();
        $this->assertSame('Zielone Biuro', $company->name);
        $this->assertSame('1234567890', $company->nip);
        $this->assertSame('Inna firma', $otherCompany->refresh()->name);
    }

    public function test_nip_must_have_ten_digits()
    {
        $this->actingAs($this->employer())
            ->put('/employer/company', ['name' => 'Zielone Biuro', 'nip' => '12345'])
            ->assertSessionHasErrors('nip');
    }

    public function test_company_description_asking_about_family_plans_is_rejected()
    {
        $employer = $this->employer();
        $originalDescription = $employer->company->description;

        $this->actingAs($employer)
            ->put('/employer/company', ['name' => 'Zielone Biuro', 'description' => 'Szukamy osób, które nie planują powiększenia rodziny.'])
            ->assertSessionHasErrors('description');

        $this->assertSame($originalDescription, $employer->company->refresh()->description);
    }
}
