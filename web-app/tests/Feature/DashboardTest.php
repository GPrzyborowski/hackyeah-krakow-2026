<?php

namespace Tests\Feature;

use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_candidates_without_published_profile_are_sent_to_onboarding()
    {
        $user = User::factory()->create();
        $user->candidateProfile()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect('/candidate/onboarding');
    }

    public function test_candidates_with_published_profile_are_sent_to_their_home()
    {
        $profile = CandidateProfile::factory()->published()->create();

        $this->actingAs($profile->user)->get(route('dashboard'))->assertRedirect('/candidate');
    }

    public function test_employers_are_sent_to_their_offers()
    {
        $user = User::factory()->employer()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect('/employer/offers');
    }

    public function test_admins_are_sent_to_the_admin_panel()
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect('/admin');
    }
}
