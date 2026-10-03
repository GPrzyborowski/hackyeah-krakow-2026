<?php

namespace App\Services\Mailing;

use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Sends one scheduled e-mail at a time; a broken mail transport (e.g. Mailpit down) is logged
 * and reported as a failure instead of aborting the whole batch.
 */
class ScheduledMailSender
{
    public function send(User|NewsletterSubscriber $recipient, Notification $notification): bool
    {
        try {
            $recipient->notifyNow($notification);

            return true;
        } catch (TransportExceptionInterface $exception) {
            Log::warning('Scheduled e-mail could not be sent.', [
                'notification' => $notification::class,
                'recipient' => $recipient->email,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
