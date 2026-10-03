<?php

namespace App\Http\Resources\Api\V1;

use App\Notifications\NotificationPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * In-app notification in the same shape as the web bell (NotificationPresenter), plus the ids of what it points to,
 * so the app can open the right screen instead of the web `url`.
 *
 * @property DatabaseNotification $resource
 */
class NotificationResource extends JsonResource
{
    private const array TARGET_KEYS = ['conversation_id', 'invitation_id', 'job_share_pair_id', 'job_offer_id', 'company_id'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource->data;

        return [
            ...NotificationPresenter::present($this->resource),
            'target' => (object) array_intersect_key($data, array_flip(self::TARGET_KEYS)),
        ];
    }
}
