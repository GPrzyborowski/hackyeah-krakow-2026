<?php

namespace Tests\Feature\Employer;

use App\Models\JobOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmployerAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function employerPages(): array
    {
        return [
            'offers' => ['/employer/offers'],
            'offer form' => ['/employer/offers/create'],
            'candidates' => ['/employer/candidates'],
            'invitations' => ['/employer/invitations'],
            'company' => ['/employer/company'],
            'skills' => ['/employer/skills?q=re'],
        ];
    }

    #[DataProvider('employerPages')]
    public function test_guests_are_redirected_to_login(string $uri)
    {
        $this->get($uri)->assertRedirect(route('login'));
    }

    #[DataProvider('employerPages')]
    public function test_candidates_are_forbidden(string $uri)
    {
        $this->actingAs(User::factory()->create())->get($uri)->assertForbidden();
    }

    public function test_candidates_cannot_create_offers()
    {
        $this->actingAs(User::factory()->create())
            ->post('/employer/offers', ['action' => 'draft', 'title' => 'X'])
            ->assertForbidden();

        $this->assertSame(0, JobOffer::count());
    }

    public function test_employer_without_company_is_forbidden()
    {
        $employer = User::factory()->employer()->create(['company_id' => null]);

        $this->actingAs($employer)->get('/employer/offers')->assertForbidden();
    }
}
