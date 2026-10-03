<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A recruiter of the employer's own company.
 *
 * @property User $resource
 */
class CompanyMemberResource extends JsonResource
{
    public function __construct(User $resource, public string $joinedAt)
    {
        parent::__construct($resource);
    }

    /**
     * @return array{id: int, name: string, email: string, joined_at: string, is_current_user: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'joined_at' => $this->joinedAt,
            'is_current_user' => $this->resource->is($request->user()),
        ];
    }
}
