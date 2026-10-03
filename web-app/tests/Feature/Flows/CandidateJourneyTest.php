<?php

namespace Tests\Feature\Flows;

use App\Enums\CvStatus;
use App\Enums\EmploymentFraction;
use App\Enums\SkillImportance;
use App\Enums\UserRole;
use App\Enums\WorkMode;
use App\Models\Company;
use App\Models\JobOffer;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CandidateJourneyTest extends TestCase
{
    use InteractsWithFlows, RefreshDatabase;

    public function test_new_candidate_registers_builds_profile_from_pasted_cv_publishes_and_shows_interest(): void
    {
        $recruitment = Skill::findOrCreateByName('Rekrutacja IT');
        $onboarding = Skill::findOrCreateByName('Onboarding');
        Skill::findOrCreateByName('Księgowość');
        $company = Company::factory()->create(['name' => 'Zielone Biuro']);
        $matchingOffer = JobOffer::factory()->published()->for($company)->create([
            'title' => 'Specjalistka ds. rekrutacji',
            'start_date' => '2027-09-01',
            'work_mode' => WorkMode::Hybrid,
            'employment_fraction' => EmploymentFraction::ThreeFifths,
        ]);
        $matchingOffer->skills()->attach($recruitment, ['importance' => SkillImportance::Required->value]);
        $matchingOffer->skills()->attach($onboarding, ['importance' => SkillImportance::NiceToHave->value]);
        $draftOffer = JobOffer::factory()->for($company)->create(['title' => 'Szkic oferty', 'start_date' => '2027-09-01']);
        $draftOffer->skills()->attach($recruitment, ['importance' => SkillImportance::Required->value]);

        $this->post(route('register.store'), [
            'name' => 'Marta Zawadzka',
            'email' => 'marta@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'candidate',
        ])->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'marta@example.test')->sole();
        $this->assertSame(UserRole::Candidate, $user->role);
        $profile = $user->candidateProfile;
        $this->assertNotNull($profile);
        $this->assertFalse($profile->isPublished());

        $this->get(route('dashboard'))->assertRedirect(route('candidate.onboarding.show'));
        $this->get(route('candidate.home'))->assertRedirect(route('candidate.onboarding.show'));

        $this->get(route('candidate.onboarding.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/Onboarding')
                ->where('step', 2)
                ->where('profile.anonymous_name', 'Marta Z.')
                ->has('skills', 0));

        $this->post(route('candidate.onboarding.cv'), [
            'cv_text' => 'Doświadczenie: 6 lat w software house. Zakres: rekrutacja IT, onboarding nowych pracowników.',
        ])->assertRedirect(route('candidate.onboarding.show', ['step' => 2]));

        $profile->refresh();
        $this->assertSame(CvStatus::Parsed, $profile->cv_status);
        $this->assertSame(0, $profile->confirmedSkills()->count());
        $this->get(route('candidate.onboarding.show', ['step' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('step', 2)
                ->has('skills', 2)
                ->where('skills.0.name', 'Onboarding')
                ->where('skills.0.source', 'ai')
                ->where('skills.0.confirmed', false)
                ->where('skills.1.name', 'Rekrutacja IT')
                ->where('skills.1.confirmed', false));

        $this->post(route('candidate.onboarding.skills.confirm'))
            ->assertRedirect(route('candidate.onboarding.show', ['step' => 3]));
        $this->assertEqualsCanonicalizing(['Rekrutacja IT', 'Onboarding'], $profile->confirmedSkills()->pluck('name')->all());

        $this->put(route('candidate.onboarding.preferences'), [
            'stage' => 'pregnant',
            'headline' => 'Specjalistka ds. rekrutacji IT',
            'years_of_experience' => 6,
            'city' => 'Kraków',
            'work_modes' => ['remote', 'hybrid'],
            'employment_fractions' => ['3/5', '1'],
            'wants_flexible_hours' => true,
            'open_to_job_sharing' => false,
            'available_from' => '2027-09-01',
            'leave_starts_on' => '2027-03-14',
            'due_date' => '2027-04-10',
        ])->assertRedirect(route('candidate.onboarding.show', ['step' => 4]));

        $this->patch(route('candidate.onboarding.privacy'), ['allow_direct_messages' => true])->assertRedirect();

        $this->post(route('candidate.onboarding.publish'), ['show_availability_instead_of_gap' => true])
            ->assertRedirect(route('candidate.home'));

        $profile->refresh();
        $this->assertTrue($profile->isPublished());
        $this->assertSame(4, $profile->onboarding_step);
        $this->assertTrue($profile->allow_direct_messages);

        // A real browser sends each request to a fresh app; drop the guard's cached user and its loaded profile.
        $this->actingAs($user->fresh());
        $this->get(route('dashboard'))->assertRedirect(route('candidate.home'));
        $this->get(route('candidate.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/Home')
                ->where('firstName', 'Marta')
                ->has('topOffers', 1)
                ->where('topOffers.0.id', $matchingOffer->id)
                ->where('topOffers.0.score', 100));

        $this->get(route('candidate.offers.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/offers/Index')
                ->where('hasConfirmedSkills', true)
                ->has('offers', 1)
                ->where('offers.0.id', $matchingOffer->id)
                ->where('offers.0.is_interested', false));

        $this->get(route('candidate.offers.show', $draftOffer))->assertNotFound();
        $this->post(route('candidate.offers.interest.store', $draftOffer))->assertNotFound();

        $this->from(route('candidate.offers.show', $matchingOffer))
            ->post(route('candidate.offers.interest.store', $matchingOffer))
            ->assertRedirect(route('candidate.offers.show', $matchingOffer));

        $this->assertDatabaseHas('offer_interests', ['candidate_profile_id' => $profile->id, 'job_offer_id' => $matchingOffer->id]);
        $this->get(route('candidate.offers.show', $matchingOffer))
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/offers/Show')
                ->where('offer.id', $matchingOffer->id)
                ->where('offer.is_interested', true));
    }
}
