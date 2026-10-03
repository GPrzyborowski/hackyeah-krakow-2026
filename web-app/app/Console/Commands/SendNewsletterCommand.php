<?php

namespace App\Console\Commands;

use App\Models\NewsletterSubscriber;
use App\Notifications\WeeklyNewsletter;
use App\Services\Mailing\NewsletterIssue;
use App\Services\Mailing\ScheduledMailSender;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('mumjobs:send-newsletter {--dry-run : List recipients and articles without sending anything}')]
#[Description('Send the newest blog articles of the last week to confirmed newsletter subscribers')]
class SendNewsletterCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(NewsletterIssue $issue, ScheduledMailSender $sender): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $recentArticles = $issue->recentArticles();

        if ($recentArticles->isEmpty()) {
            $this->components->info('No articles published in the last week – nothing to send.');

            return self::SUCCESS;
        }

        $sentCount = 0;
        $failedCount = 0;

        NewsletterSubscriber::query()->active()->chunkById(100, function ($subscribers) use ($issue, $sender, $recentArticles, $isDryRun, &$sentCount, &$failedCount): void {
            foreach ($subscribers as $subscriber) {
                $articles = $issue->articlesFor($subscriber, $recentArticles);

                if ($articles->isEmpty()) {
                    continue;
                }

                if ($isDryRun) {
                    $this->components->twoColumnDetail($subscriber->email, $articles->pluck('title')->implode(', '));
                    $sentCount++;

                    continue;
                }

                if ($sender->send($subscriber, new WeeklyNewsletter($articles))) {
                    $issue->recordDelivery($subscriber, $articles);
                    $sentCount++;
                } else {
                    $failedCount++;
                }
            }
        });

        $this->components->info($isDryRun
            ? "Dry run: {$sentCount} newsletter(s) would be sent."
            : "Sent {$sentCount} newsletter(s), {$failedCount} failed.");

        return self::SUCCESS;
    }
}
