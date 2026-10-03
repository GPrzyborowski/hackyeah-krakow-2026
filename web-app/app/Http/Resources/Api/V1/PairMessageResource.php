<?php

namespace App\Http\Resources\Api\V1;

use App\Models\JobShareMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Message in a pair's private chat; the author is shown by first name only. Expects `author` to be loaded.
 *
 * @property JobShareMessage $resource
 */
class PairMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $message = $this->resource;
        $nameParts = preg_split('/\s+/', trim($message->author->name)) ?: [];

        return [
            'id' => $message->id,
            'body' => $message->body,
            'author_name' => $nameParts[0] ?? $message->author->name,
            'is_mine' => $message->user_id === $request->user()?->id,
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }
}
