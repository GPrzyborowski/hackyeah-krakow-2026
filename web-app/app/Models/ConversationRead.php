<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How far a participant has read a team chat (messages up to `last_read_message_id` are read by her).
 *
 * @property int $id
 * @property int $conversation_id
 * @property int $user_id
 * @property int $last_read_message_id
 */
#[Fillable(['conversation_id', 'user_id', 'last_read_message_id'])]
class ConversationRead extends Model
{
    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
