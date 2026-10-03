<?php

namespace App\Http\Controllers\Newsletter;

use App\Http\Controllers\Controller;
use App\Http\Requests\Newsletter\SubscribeRequest;
use App\Models\NewsletterSubscriber;
use App\Notifications\NewsletterConfirmation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class NewsletterController extends Controller
{
    /**
     * Start a double opt-in; the response never reveals whether the address was already subscribed.
     */
    public function store(SubscribeRequest $request): RedirectResponse
    {
        $subscriber = NewsletterSubscriber::query()->firstOrNew([
            'email' => mb_strtolower(trim($request->validated('email'))),
        ]);

        if (! $subscriber->isActive()) {
            $subscriber->fill([
                'token' => $subscriber->token ?? NewsletterSubscriber::generateToken(),
                'confirmed_at' => null,
                'unsubscribed_at' => null,
            ])->save();

            $subscriber->notify(new NewsletterConfirmation);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sprawdź skrzynkę i potwierdź zapis.']);

        return back();
    }

    /**
     * Signed link from the confirmation e-mail.
     */
    public function confirm(string $token): Response
    {
        $subscriber = NewsletterSubscriber::query()->where('token', $token)->firstOrFail();

        if ($subscriber->confirmed_at === null) {
            $subscriber->update(['confirmed_at' => now(), 'unsubscribed_at' => null]);
        }

        return Inertia::render('public/newsletter/Status', [
            'status' => 'confirmed',
            'unsubscribeToken' => $subscriber->token,
        ]);
    }

    /**
     * One-click opt-out from any newsletter e-mail.
     */
    public function unsubscribe(string $token): Response
    {
        $subscriber = NewsletterSubscriber::query()->where('token', $token)->firstOrFail();

        if ($subscriber->unsubscribed_at === null) {
            $subscriber->update(['unsubscribed_at' => now()]);
        }

        return Inertia::render('public/newsletter/Status', [
            'status' => 'unsubscribed',
            'unsubscribeToken' => null,
        ]);
    }
}
