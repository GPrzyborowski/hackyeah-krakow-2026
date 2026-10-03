<?php

namespace Tests\Feature\Api\Employer;

use App\Enums\ReviewStatus;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Employer\InteractsWithEmployerFixtures;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use InteractsWithEmployerFixtures, RefreshDatabase;

    public function test_employer_sees_own_company_with_approved_review_averages(): void
    {
        $employer = $this->employer();
        CompanyReview::factory()->for($employer->company)->create(['rating_return' => 5, 'rating_flexibility' => 4, 'rating_no_pregnancy_questions' => 3]);
        CompanyReview::factory()->for($employer->company)->create(['rating_return' => 4, 'rating_flexibility' => 4, 'rating_no_pregnancy_questions' => 5]);
        CompanyReview::factory()->for($employer->company)->create(['status' => ReviewStatus::Pending, 'rating_return' => 1]);
        CompanyReview::factory()->create();
        Sanctum::actingAs($employer);

        $this->getJson('/api/v1/employer/company')
            ->assertOk()
            ->assertJsonPath('data.id', $employer->company_id)
            ->assertJsonCount(2, 'data.reviews')
            ->assertJsonPath('data.ratings.count', 2)
            ->assertJsonPath('data.ratings.return', 4.5)
            ->assertJsonPath('data.ratings.no_pregnancy_questions', 4)
            ->assertJsonPath('data.ratings.overall', 4.2);
    }

    public function test_employer_updates_own_company_and_nip_is_normalised(): void
    {
        $employer = $this->employer();
        $otherCompany = Company::factory()->create(['name' => 'Inna firma']);
        Sanctum::actingAs($employer);

        $this->putJson('/api/v1/employer/company', ['name' => 'Zielone Biuro', 'nip' => '123-456-32-18', 'city' => 'Kraków', 'description' => 'Elastyczne godziny.'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Zielone Biuro')
            ->assertJsonPath('data.nip', '1234563218');

        $this->assertSame('Zielone Biuro', $employer->company->refresh()->name);
        $this->assertSame('Inna firma', $otherCompany->refresh()->name);
    }

    public function test_nip_must_have_ten_digits(): void
    {
        Sanctum::actingAs($this->employer());

        $this->putJson('/api/v1/employer/company', ['name' => 'Zielone Biuro', 'nip' => '12345'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nip' => 'NIP musi składać się z 10 cyfr.']);
    }

    public function test_nip_checksum_is_validated(): void
    {
        Sanctum::actingAs($this->employer());

        $this->putJson('/api/v1/employer/company', ['name' => 'Zielone Biuro', 'nip' => '1234567890'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nip' => 'Podany NIP jest nieprawidłowy.']);
    }

    public function test_nip_of_another_company_is_rejected_but_own_nip_can_be_resubmitted(): void
    {
        $employer = $this->employer();
        $employer->company->update(['nip' => '1234563218']);
        Company::factory()->create(['nip' => '5260250274']);
        Sanctum::actingAs($employer);

        $this->putJson('/api/v1/employer/company', ['name' => 'Zielone Biuro', 'nip' => '526-025-02-74'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nip' => 'Firma z tym NIP-em ma już konto w MomJobs.']);

        $this->putJson('/api/v1/employer/company', ['name' => 'Zielone Biuro', 'nip' => '1234563218'])
            ->assertOk();
    }

    public function test_description_asking_about_family_plans_is_rejected(): void
    {
        $employer = $this->employer();
        $originalDescription = $employer->company->description;
        Sanctum::actingAs($employer);

        $this->putJson('/api/v1/employer/company', ['name' => 'Zielone Biuro', 'description' => 'Szukamy osób, które nie planują powiększenia rodziny.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('description');

        $this->assertSame($originalDescription, $employer->company->refresh()->description);
    }

    public function test_guests_get_401(): void
    {
        $this->getJson('/api/v1/employer/company')->assertUnauthorized();
    }

    public function test_candidates_are_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/employer/company')->assertForbidden();
    }

    public function test_unverified_employers_are_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->employer()->unverified()->create());

        $this->getJson('/api/v1/employer/company')
            ->assertForbidden()
            ->assertJsonPath('message', 'Potwierdź swój adres e-mail – link znajdziesz w skrzynce. Możesz poprosić o nowy link.')
            ->assertJsonPath('email_verification_required', true);
    }

    public function test_unverified_employer_gets_json_even_without_an_accept_header(): void
    {
        $token = User::factory()->employer()->unverified()->create()->createToken('iPhone')->plainTextToken;

        $this->withToken($token)
            ->get('/api/v1/employer/company')
            ->assertForbidden()
            ->assertJsonPath('email_verification_required', true);
    }

    public function test_employer_without_company_is_forbidden(): void
    {
        $employer = $this->employer();
        $employer->forceFill(['company_id' => null])->save();
        Sanctum::actingAs($employer);

        $this->getJson('/api/v1/employer/company')->assertForbidden();
    }
}
