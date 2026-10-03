<?php

namespace Tests\Feature\Candidate;

use App\Enums\CandidateStage;
use App\Models\CandidateProfile;
use App\Services\Candidate\ReturnCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReturnCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Date::setTestNow('2026-10-03');
    }

    /**
     * @param  array<string, string|null>  $dates
     */
    #[DataProvider('phases')]
    public function test_current_phase_follows_the_stage_and_dates(?CandidateStage $stage, array $dates, string $expectedPhase): void
    {
        $profile = CandidateProfile::factory()->make([
            'stage' => $stage,
            'due_date' => null,
            'leave_starts_on' => null,
            'available_from' => null,
            ...$dates,
        ]);

        $this->assertSame($expectedPhase, app(ReturnCalendar::class)->forProfile($profile)['current_phase']);
    }

    /**
     * @return array<string, array{CandidateStage|null, array<string, string|null>, string}>
     */
    public static function phases(): array
    {
        return [
            'pregnant without dates' => [CandidateStage::Pregnant, [], 'pregnancy'],
            'pregnant before leave' => [CandidateStage::Pregnant, ['due_date' => '2027-01-02', 'leave_starts_on' => '2026-12-01', 'available_from' => '2027-09-01'], 'pregnancy'],
            'pregnant on leave' => [CandidateStage::Pregnant, ['due_date' => '2026-11-20', 'leave_starts_on' => '2026-10-01', 'available_from' => '2027-09-01'], 'leave'],
            'pregnant after due date without leave date' => [CandidateStage::Pregnant, ['due_date' => '2026-09-20', 'available_from' => '2027-09-01'], 'leave'],
            'pregnant and ready' => [CandidateStage::Pregnant, ['available_from' => '2026-10-01'], 'ready'],
            'after leave without dates' => [CandidateStage::AfterLeave, [], 'return'],
            'after leave with a far start date' => [CandidateStage::AfterLeave, ['leave_starts_on' => '2026-03-01', 'available_from' => '2027-08-01'], 'leave'],
            'after leave starting within weeks' => [CandidateStage::AfterLeave, ['available_from' => '2026-11-02'], 'return'],
            'after leave and ready' => [CandidateStage::AfterLeave, ['available_from' => '2026-10-03'], 'ready'],
            'no stage and no dates' => [null, [], 'ready'],
            'no stage with a future start date only' => [null, ['available_from' => '2027-09-01'], 'ready'],
            'no stage on leave' => [null, ['leave_starts_on' => '2026-09-01', 'available_from' => '2027-09-01'], 'leave'],
        ];
    }

    public function test_pregnant_calendar_has_a_pregnancy_week_and_pregnancy_phases(): void
    {
        $profile = CandidateProfile::factory()->make(['stage' => CandidateStage::Pregnant, 'due_date' => '2027-01-02']);

        $calendar = app(ReturnCalendar::class)->forProfile($profile);

        $this->assertSame('pregnant', $calendar['stage']);
        $this->assertSame('W ciąży', $calendar['stage_label']);
        $this->assertSame(['pregnancy', 'leave', 'ready'], $calendar['phases']);
        $this->assertSame(27, $calendar['pregnancy_week']);
        $this->assertSame('2027-01-02', $calendar['due_date']);
    }

    public function test_after_leave_calendar_has_no_pregnancy_week_or_due_date(): void
    {
        $profile = CandidateProfile::factory()->make(['stage' => CandidateStage::AfterLeave, 'due_date' => '2027-01-02']);

        $calendar = app(ReturnCalendar::class)->forProfile($profile);

        $this->assertSame('after_leave', $calendar['stage']);
        $this->assertSame('Po urlopie macierzyńskim', $calendar['stage_label']);
        $this->assertSame(['leave', 'return', 'ready'], $calendar['phases']);
        $this->assertNull($calendar['pregnancy_week']);
        $this->assertNull($calendar['due_date']);
    }
}
