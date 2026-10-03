<?php

namespace Tests\Feature\Console;

use App\Enums\EmploymentFraction;
use App\Enums\SkillImportance;
use App\Enums\WorkMode;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Models\Skill;
use App\Notifications\JobAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SendJobAlertsCommandTest extends TestCase
{
    use RefreshDatabase;

    private Skill $recruitment;

    private CandidateProfile $candidate;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->recruitment = Skill::factory()->create(['name' => 'Rekrutacja IT']);
        $this->candidate = CandidateProfile::factory()->published()->create([
            'available_from' => '2027-09-01',
            'work_modes' => [WorkMode::Remote->value],
            'employment_fractions' => [EmploymentFraction::ThreeFifths->value],
        ]);
        $this->candidate->skills()->attach($this->recruitment, ['source' => 'manual', 'confirmed_at' => now()]);
    }

    public function test_it_e_mails_new_well_matched_offers_and_skips_weak_matches(): void
    {
        $goodMatch = $this->offer(['title' => 'Rekruterka IT', 'salary_min' => 8000, 'salary_max' => 10000]);
        $weakMatch = $this->offer(requiredSkill: Skill::factory()->create());

        $this->artisan('mumjobs:send-job-alerts')->assertSuccessful();

        Notification::assertSentTo($this->candidate->user, JobAlert::class, function (JobAlert $notification) use ($goodMatch): bool {
            $mail = $notification->toMail($this->candidate->user);
            $lines = implode("\n", $mail->introLines);

            return $notification->matches->pluck('offer.id')->all() === [$goodMatch->id]
                && str_contains($lines, 'Dopasowanie 100%')
                && str_contains($lines, '8 000–10 000 zł brutto')
                && str_contains($lines, route('candidate.offers.show', $goodMatch));
        });
        $this->assertDatabaseHas('job_alert_deliveries', ['candidate_profile_id' => $this->candidate->id, 'job_offer_id' => $goodMatch->id]);
        $this->assertDatabaseMissing('job_alert_deliveries', ['job_offer_id' => $weakMatch->id]);
    }

    public function test_offers_older_than_a_week_or_starting_too_early_are_skipped(): void
    {
        $this->offer(['published_at' => now()->subDays(8)]);
        $this->offer(['start_date' => '2027-07-01']);

        $this->artisan('mumjobs:send-job-alerts')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_an_offer_is_alerted_to_a_candidate_only_once(): void
    {
        $this->offer();

        $this->artisan('mumjobs:send-job-alerts')->assertSuccessful();
        $this->artisan('mumjobs:send-job-alerts')->assertSuccessful();

        Notification::assertSentToTimes($this->candidate->user, JobAlert::class, 1);
    }

    public function test_at_most_five_offers_are_sent(): void
    {
        foreach (range(1, 6) as $index) {
            $this->offer();
        }

        $this->artisan('mumjobs:send-job-alerts')->assertSuccessful();

        Notification::assertSentTo($this->candidate->user, JobAlert::class, fn (JobAlert $notification): bool => $notification->matches->count() === 5);
    }

    public function test_candidates_who_turned_alerts_off_or_are_unpublished_get_nothing(): void
    {
        $this->offer();
        $this->candidate->update(['job_alerts_enabled' => false]);
        $unpublished = CandidateProfile::factory()->create(['available_from' => '2027-09-01']);
        $unpublished->skills()->attach($this->recruitment, ['source' => 'manual', 'confirmed_at' => now()]);

        $this->artisan('mumjobs:send-job-alerts')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_dry_run_sends_and_records_nothing(): void
    {
        $this->offer();

        $this->artisan('mumjobs:send-job-alerts', ['--dry-run' => true])
            ->expectsOutputToContain($this->candidate->user->email)
            ->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertDatabaseCount('job_alert_deliveries', 0);
    }

    public function test_candidate_can_turn_job_alerts_off_on_web_and_api(): void
    {
        $user = $this->candidate->user;

        $this->actingAs($user)
            ->patch(route('candidate.onboarding.privacy'), ['job_alerts_enabled' => false])
            ->assertRedirect();
        $this->assertFalse($this->candidate->refresh()->job_alerts_enabled);

        Sanctum::actingAs($user);
        $this->patchJson('/api/v1/candidate/profile/privacy', ['job_alerts_enabled' => true])
            ->assertOk()
            ->assertJsonPath('data.privacy.job_alerts_enabled', true);
    }

    /**
     * A published offer requiring one skill; by default a 100% match for the candidate starting before her.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function offer(array $attributes = [], ?Skill $requiredSkill = null): JobOffer
    {
        $offer = JobOffer::factory()->published()->create([
            'start_date' => '2027-09-01',
            'work_mode' => WorkMode::Remote,
            'employment_fraction' => EmploymentFraction::ThreeFifths,
            ...$attributes,
        ]);
        $offer->skills()->attach($requiredSkill ?? $this->recruitment, ['importance' => SkillImportance::Required->value]);

        return $offer;
    }
}
