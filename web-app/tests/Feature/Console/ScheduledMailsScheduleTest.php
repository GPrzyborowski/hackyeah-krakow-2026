<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduledMailsScheduleTest extends TestCase
{
    public function test_weekly_mail_commands_are_listed_in_the_schedule(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('momjobs:send-job-alerts')
            ->expectsOutputToContain('momjobs:send-newsletter')
            ->assertSuccessful();
    }

    public function test_weekly_mail_commands_run_on_monday_morning_warsaw_time(): void
    {
        $this->assertSame(['0 8 * * 1', 'Europe/Warsaw'], $this->scheduleOf('momjobs:send-job-alerts'));
        $this->assertSame(['0 9 * * 1', 'Europe/Warsaw'], $this->scheduleOf('momjobs:send-newsletter'));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function scheduleOf(string $command): array
    {
        $event = collect(app(Schedule::class)->events())
            ->sole(fn (Event $event): bool => str_contains((string) $event->command, $command));

        return [$event->expression, (string) $event->timezone];
    }
}
