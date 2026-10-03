<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DeviceTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Push notification token of a mobile device (FCM on Android, APNs on iOS). Stored only; nothing is sent yet.
 * Bound to the Sanctum token that registered it: revoking that API token deletes the row (FK cascade).
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $personal_access_token_id
 * @property string $token
 * @property string $platform
 * @property CarbonImmutable|null $last_used_at
 */
#[Fillable(['user_id', 'personal_access_token_id', 'token', 'platform', 'last_used_at'])]
class DeviceToken extends Model
{
    /** @use HasFactory<DeviceTokenFactory> */
    use HasFactory;

    public const array PLATFORMS = ['ios', 'android'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }
}
