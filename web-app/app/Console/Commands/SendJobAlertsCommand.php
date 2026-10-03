<?php

namespace App\Console\Commands;

use App\Notifications\JobAlert;
use App\Services\Mailing\JobAlertSelector;
use App\Services\Mailing\ScheduledMailSender;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('mumjobs:send-job-alerts {--dry-run : List recipients and offers without sending anything}')]
#[Description('E-mail published candidates up to 5 new, well-matched offers they can start in time')]
class SendJobAlertsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(JobAlertSelector $selector, ScheduledMailSender $sender): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $recentOffers = $selector->recentOffers();

        if ($recentOffers->isEmpty()) {
            $this->components->info('No offers published in the last week – nothing to send.');

            return self::SUCCESS;
        }

        $sentCount = 0;
        $failedCount = 0;

        $selector->recipients()->chunkById(100, function ($candidates) use ($selector, $sender, $recentOffers, $isDryRun, &$sentCount, &$failedCount): void {
            foreach ($candidates as $candidate) {
                $matches = $selector->offersFor($candidate, $recentOffers);

                if ($matches->isEmpty()) {
                    continue;
                }

                if ($isDryRun) {
                    $this->components->twoColumnDetail($candidate->user->email, $matches->map(fn (array $row): string => "{$row['offer']->title} ({$row['match']->score}%)")->implode(', '));
                    $sentCount++;

                    continue;
                }

                if ($sender->send($candidate->user, new JobAlert($matches))) {
                    $selector->recordDelivery($candidate, $matches->pluck('offer'));
                    $sentCount++;
                } else {
                    $failedCount++;
                }
            }
        });

        $this->components->info($isDryRun
            ? "Dry run: {$sentCount} job alert(s) would be sent."
            : "Sent {$sentCount} job alert(s), {$failedCount} failed.");

        return self::SUCCESS;
    }
}
