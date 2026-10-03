<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AssistantMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One turn of the legal assistant chat; assistant answers carry citations [{type: legal|article, label, url}].
 *
 * @property AssistantMessage $resource
 */
class AssistantMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $message = $this->resource;

        return [
            'id' => $message->id,
            'role' => $message->role->value,
            'content' => $message->content,
            'citations' => array_map(fn (array $citation): array => [
                'type' => $citation['type'],
                'label' => $citation['label'],
                'url' => $citation['url'] ?? null,
            ], $message->citations ?? []),
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
