<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\NewsletterSubscriberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $email
 * @property string $token
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $unsubscribed_at
 * @property CarbonImmutable|null $last_sent_at
 */
#[Fillable(['email', 'token', 'confirmed_at', 'unsubscribed_at', 'last_sent_at'])]
class NewsletterSubscriber extends Model
{
    /** @use HasFactory<NewsletterSubscriberFactory> */
    use HasFactory, Notifiable;

    public static function generateToken(): string
    {
        return Str::random(48);
    }

    /**
     * Articles already sent to this subscriber in a weekly newsletter.
     *
     * @return BelongsToMany<Article, $this>
     */
    public function deliveredArticles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'newsletter_deliveries')->withPivot('sent_at');
    }

    /**
     * Confirmed and not unsubscribed subscribers, i.e. the weekly e-mail audience.
     *
     * @param  Builder<NewsletterSubscriber>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNotNull('confirmed_at')->whereNull('unsubscribed_at');
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
            'last_sent_at' => 'datetime',
        ];
    }
}
