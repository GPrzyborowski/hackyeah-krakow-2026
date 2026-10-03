<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A skill suggestion for the offer tag inputs.
 *
 * @property Skill $resource
 */
class EmployerSkillResource extends JsonResource
{
    /**
     * @return array{id: int, name: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
        ];
    }
}
