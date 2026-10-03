<?php

namespace Tests\Feature\Newsletter;

use App\Models\NewsletterSubscriber;
use App\Notifications\NewsletterConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Notification::fake();
    }

    public function test_subscribing_stores_a_pending_subscriber_and_sends_a_signed_confirmation_link(): void
    {
        $this->from(route('blog.index'))
            ->post(route('newsletter.store'), ['email' => ' Anna@Example.com '])
            ->assertRedirect(route('blog.index'))
            ->assertSessionHasNoErrors();

        $subscriber = NewsletterSubscriber::query()->sole();
        $this->assertSame('anna@example.com', $subscriber->email);
        $this->assertNull($subscriber->confirmed_at);

        Notification::assertSentTo($subscriber, NewsletterConfirmation::class, function (NewsletterConfirmation $notification) use ($subscriber): bool {
            $link = $notification->toMail($subscriber)->actionUrl;

            return str_contains($link, $subscriber->token) && URL::hasValidSignature(request()->create($link));
        });
    }

    public function test_invalid_email_is_rejected(): void
    {
        $this->post(route('newsletter.store'), ['email' => 'nie-email'])->assertSessionHasErrors('email');

        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }

    public function test_duplicate_active_subscription_is_not_revealed_and_sends_nothing(): void
    {
        NewsletterSubscriber::factory()->confirmed()->create(['email' => 'anna@example.com']);

        $this->post(route('newsletter.store'), ['email' => 'anna@example.com'])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('newsletter_subscribers', 1);
        Notification::assertNothingSent();
    }

    public function test_unsubscribed_address_can_subscribe_again_after_confirming(): void
    {
        $subscriber = NewsletterSubscriber::factory()->unsubscribed()->create(['email' => 'anna@example.com']);

        $this->post(route('newsletter.store'), ['email' => 'anna@example.com']);

        $subscriber->refresh();
        $this->assertNull($subscriber->unsubscribed_at);
        $this->assertNull($subscriber->confirmed_at);
        Notification::assertSentTo($subscriber, NewsletterConfirmation::class);
    }

    public function test_signed_link_confirms_the_subscription(): void
    {
        $subscriber = NewsletterSubscriber::factory()->create();

        $this->get(URL::temporarySignedRoute('newsletter.confirm', now()->addDay(), ['token' => $subscriber->token]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/newsletter/Status')
                ->where('status', 'confirmed'));

        $this->assertTrue($subscriber->refresh()->isActive());
    }

    public function test_unsigned_confirmation_link_is_rejected(): void
    {
        $subscriber = NewsletterSubscriber::factory()->create();

        $this->get(route('newsletter.confirm', $subscriber->token))->assertForbidden();

        $this->assertNull($subscriber->refresh()->confirmed_at);
    }

    public function test_one_click_unsubscribe(): void
    {
        $subscriber = NewsletterSubscriber::factory()->confirmed()->create();

        $this->get(route('newsletter.unsubscribe', $subscriber->token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('status', 'unsubscribed'));

        $this->assertFalse($subscriber->refresh()->isActive());
        $this->get(route('newsletter.unsubscribe', 'unknown-token'))->assertNotFound();
    }

    public function test_subscribing_is_throttled_with_a_friendly_error(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('newsletter.store'), ['email' => "anna{$attempt}@example.com"]);
        }

        $this->from(route('blog.index'))
            ->post(route('newsletter.store'), ['email' => 'anna6@example.com'])
            ->assertRedirect(route('blog.index'))
            ->assertSessionHasErrors(['email' => 'Za dużo prób zapisu. Spróbuj ponownie za minutę.']);
    }
}
