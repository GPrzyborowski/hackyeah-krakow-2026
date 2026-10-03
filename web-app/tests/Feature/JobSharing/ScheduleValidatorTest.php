<?php

namespace Tests\Feature\JobSharing;

use App\Services\JobSharing\ScheduleValidator;
use App\Services\JobSharing\Workday;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScheduleValidatorTest extends TestCase
{
    /**
     * @return array<string, array{0: list<array{0: string, 1: string}>, 1: string|null}>
     */
    public static function schedules(): array
    {
        return [
            'even split' => [[['08:00', '12:00'], ['12:00', '16:00']], null],
            'uneven split in reverse order' => [[['13:30', '16:00'], ['08:00', '13:30']], null],
            'gap in the middle' => [[['08:00', '11:00'], ['12:00', '16:00']], 'Nikt nie pracuje między 11:00 a 12:00'],
            'afternoon not covered' => [[['08:00', '12:00'], ['12:00', '15:00']], 'Nikt nie pracuje między 15:00 a 16:00'],
            'overlap' => [[['08:00', '13:00'], ['12:00', '16:00']], 'nie mogą się nakładać'],
            'outside the workday' => [[['07:00', '12:00'], ['12:00', '16:00']], 'mieścić się w godzinach pracy 08:00–16:00'],
            'block shorter than an hour' => [[['08:00', '15:30'], ['15:30', '16:00']], 'co najmniej godzinę'],
            'end before start' => [[['12:00', '08:00'], ['12:00', '16:00']], 'Koniec bloku'],
        ];
    }

    /**
     * @param  list<array{0: string, 1: string}>  $times
     */
    #[DataProvider('schedules')]
    public function test_validates_the_split_of_the_workday(array $times, ?string $expectedError)
    {
        $blocks = [
            ['candidate_profile_id' => 1, 'starts_at' => $times[0][0], 'ends_at' => $times[0][1]],
            ['candidate_profile_id' => 2, 'starts_at' => $times[1][0], 'ends_at' => $times[1][1]],
        ];

        $errors = (new ScheduleValidator)->errors(new Workday(8 * 60, 16 * 60), [1, 2], $blocks);

        if ($expectedError === null) {
            $this->assertSame([], $errors);

            return;
        }

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString($expectedError, $errors[0]);
    }

    public function test_each_member_needs_exactly_one_block()
    {
        $errors = (new ScheduleValidator)->errors(new Workday(480, 960), [1, 2], [
            ['candidate_profile_id' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00'],
            ['candidate_profile_id' => 3, 'starts_at' => '12:00', 'ends_at' => '16:00'],
        ]);

        $this->assertSame(['Każda osoba z pary musi mieć dokładnie jeden blok godzin.'], $errors);
    }
}
