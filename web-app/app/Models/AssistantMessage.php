<?php

namespace App\Models;

use App\Enums\AssistantRole;
use Database\Factories\AssistantMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property AssistantRole $role
 * @property string $content
 * @property list<array{type: string, label: string, url?: string|null}>|null $citations
 */
#[Fillable(['user_id', 'role', 'content', 'citations'])]
class AssistantMessage extends Model
{
    /** @use HasFactory<AssistantMessageFactory> */
    use HasFactory;

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
            'role' => AssistantRole::class,
            'citations' => 'array',
        ];
    }
}
