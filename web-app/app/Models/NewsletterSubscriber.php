<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\NewsletterSubscriberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $email
 * @property string $token
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $unsubscribed_at
 */
#[Fillable(['email', 'token', 'confirmed_at', 'unsubscribed_at'])]
class NewsletterSubscriber extends Model
{
    /** @use HasFactory<NewsletterSubscriberFactory> */
    use HasFactory, Notifiable;

    public static function generateToken(): string
    {
        return Str::random(48);
    }

    /**
     * Confirmed and not unsubscribed, i.e. receives the weekly e-mail.
     */
    public function isActive(): bool
    {
        return $this->confirmed_at !== null && $this->unsubscribed_at === null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }
}
