<?php

namespace App\Console\Commands;

use App\Models\ModerationEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('mumjobs:prune-moderation-events')]
#[Description('Delete moderation log entries older than the retention period (90 days)')]
class PruneModerationEventsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deletedCount = ModerationEvent::query()
            ->where('created_at', '<', now()->subDays(ModerationEvent::RETENTION_DAYS))
            ->delete();

        $this->components->info("Deleted {$deletedCount} moderation events older than ".ModerationEvent::RETENTION_DAYS.' days.');

        return self::SUCCESS;
    }
}
