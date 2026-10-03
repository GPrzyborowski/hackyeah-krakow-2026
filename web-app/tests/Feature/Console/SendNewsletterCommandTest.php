<?php

namespace Tests\Feature\Console;

use App\Models\Article;
use App\Models\NewsletterSubscriber;
use App\Notifications\WeeklyNewsletter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Tests\TestCase;

class SendNewsletterCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_the_newest_article_of_the_week_to_active_subscribers_only(): void
    {
        Notification::fake();
        $article = Article::factory()->create(['title' => 'Powrót do pracy krok po kroku', 'published_at' => now()->subDays(2)]);
        Article::factory()->create(['published_at' => now()->subDays(10)]);
        Article::factory()->create(['published_at' => now()->addDay()]);
        $active = NewsletterSubscriber::factory()->confirmed()->create();
        $unsubscribed = NewsletterSubscriber::factory()->unsubscribed()->create();
        $unconfirmed = NewsletterSubscriber::factory()->create();

        $this->artisan('mumjobs:send-newsletter')->assertSuccessful();

        Notification::assertSentTo($active, WeeklyNewsletter::class, fn (WeeklyNewsletter $notification): bool => $notification->articles->modelKeys() === [$article->id]);
        Notification::assertNotSentTo([$unsubscribed, $unconfirmed], WeeklyNewsletter::class);
        $this->assertDatabaseHas('newsletter_deliveries', ['newsletter_subscriber_id' => $active->id, 'article_id' => $article->id]);
        $this->assertNotNull($active->refresh()->last_sent_at);
    }

    public function test_an_article_is_sent_to_a_subscriber_only_once(): void
    {
        Notification::fake();
        Article::factory()->create(['published_at' => now()->subDay()]);
        $subscriber = NewsletterSubscriber::factory()->confirmed()->create();

        $this->artisan('mumjobs:send-newsletter')->assertSuccessful();
        $this->artisan('mumjobs:send-newsletter')->assertSuccessful();

        Notification::assertSentToTimes($subscriber, WeeklyNewsletter::class, 1);
    }

    public function test_dry_run_lists_recipients_without_sending_or_recording(): void
    {
        Notification::fake();
        Article::factory()->create(['title' => 'Elastyczny etat', 'published_at' => now()->subDay()]);
        $subscriber = NewsletterSubscriber::factory()->confirmed()->create(['email' => 'anna@example.com']);

        $this->artisan('mumjobs:send-newsletter', ['--dry-run' => true])
            ->expectsOutputToContain('anna@example.com')
            ->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertDatabaseCount('newsletter_deliveries', 0);
        $this->assertNull($subscriber->refresh()->last_sent_at);
    }

    public function test_the_e_mail_carries_a_one_click_unsubscribe_link_and_header(): void
    {
        $article = Article::factory()->create(['published_at' => now()->subDay()]);
        $subscriber = NewsletterSubscriber::factory()->confirmed()->create();

        $this->artisan('mumjobs:send-newsletter')->assertSuccessful();

        $unsubscribeUrl = route('newsletter.unsubscribe', ['token' => $subscriber->token]);
        /** @var ArrayTransport $transport */
        $transport = Mail::mailer('array')->getSymfonyTransport();
        $email = $transport->messages()->sole()->getOriginalMessage();
        $this->assertInstanceOf(Email::class, $email);
        $this->assertSame("<{$unsubscribeUrl}>", $email->getHeaders()->get('List-Unsubscribe')?->getBodyAsString());
        $this->assertStringContainsString($unsubscribeUrl, (string) $email->getHtmlBody());
        $this->assertStringContainsString(route('blog.show', $article), (string) $email->getHtmlBody());
    }

    public function test_a_failing_mail_transport_is_logged_and_the_batch_continues(): void
    {
        Log::spy();
        Mail::extend('failing', fn () => new class extends ArrayTransport
        {
            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                throw new TransportException('Connection refused');
            }
        });
        config(['mail.mailers.failing' => ['transport' => 'failing'], 'mail.default' => 'failing']);
        Article::factory()->create(['published_at' => now()->subDay()]);
        NewsletterSubscriber::factory()->confirmed()->count(2)->create();

        $this->artisan('mumjobs:send-newsletter')
            ->expectsOutputToContain('Sent 0 newsletter(s), 2 failed.')
            ->assertSuccessful();

        $this->assertDatabaseCount('newsletter_deliveries', 0);
        Log::shouldHaveReceived('warning')->twice();
    }
}
