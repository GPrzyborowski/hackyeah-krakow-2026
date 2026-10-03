<?php

namespace Tests\Feature\Console;

use App\Models\ModerationEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneModerationEventsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_only_events_older_than_ninety_days(): void
    {
        $expired = ModerationEvent::factory()->create(['created_at' => now()->subDays(91)]);
        $kept = ModerationEvent::factory()->create(['created_at' => now()->subDays(89)]);

        $this->artisan('mumjobs:prune-moderation-events')->assertSuccessful();

        $this->assertModelMissing($expired);
        $this->assertModelExists($kept);
    }

    public function test_it_runs_daily_in_the_schedule(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('mumjobs:prune-moderation-events')
            ->assertSuccessful();
    }
}
