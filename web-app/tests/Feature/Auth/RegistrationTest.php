<?php

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'candidate',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertNotNull(User::firstWhere('email', 'test@example.com')->candidateProfile);
    }

    public function test_employers_register_together_with_their_company()
    {
        $this->post(route('register.store'), [
            'name' => 'Rekruterka',
            'email' => 'hr@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'employer',
            'company_name' => 'Zielone Biuro',
            'company_nip' => '526-025-09-95',
        ]);

        $user = User::firstWhere('email', 'hr@example.com');

        $this->assertTrue($user->isEmployer());
        $this->assertSame('Zielone Biuro', $user->company->name);
        $this->assertSame('5260250995', $user->company->nip);
        $this->assertNull($user->candidateProfile);
    }

    public function test_employers_need_a_valid_nip()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Rekruterka',
            'email' => 'hr@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'employer',
            'company_name' => 'Zielone Biuro',
            'company_nip' => '1234567890',
        ]);

        $response->assertSessionHasErrors('company_nip');
        $this->assertGuest();
    }

    public function test_employers_cannot_register_a_second_company_with_the_same_nip()
    {
        Company::factory()->create(['nip' => '5260250995']);

        $response = $this->post(route('register.store'), [
            'name' => 'Rekruterka',
            'email' => 'hr@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'employer',
            'company_name' => 'Zielone Biuro bis',
            'company_nip' => '5260250995',
        ]);

        $response->assertSessionHasErrors('company_nip');
        $this->assertSame(1, Company::count());
    }

    public function test_users_cannot_register_as_admin()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Intruz',
            'email' => 'admin@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertGuest();
    }
}
