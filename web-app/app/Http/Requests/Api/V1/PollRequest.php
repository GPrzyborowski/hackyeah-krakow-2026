<?php

namespace App\Http\Requests\Api\V1;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Polling parameters of list endpoints: `since` (ISO 8601, only items created after it) and `after_id`
 * (only items with a greater id; immune to equal timestamps, preferred for chat threads).
 */
class PollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'since' => ['nullable', 'date'],
            'after_id' => ['nullable', 'integer', 'min:0'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * The `since` cursor converted to the application timezone (timestamps are stored in it).
     */
    public function since(): ?CarbonImmutable
    {
        $since = $this->validated('since');

        return is_string($since) ? CarbonImmutable::parse($since)->setTimezone(config('app.timezone')) : null;
    }

    /**
     * Restrict the query to items newer than the polling cursor.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applyTo(Builder $query, string $createdAtColumn = 'created_at', string $idColumn = 'id'): Builder
    {
        $since = $this->since();
        $afterId = $this->validated('after_id');

        return $query
            ->when($since !== null, fn (Builder $query) => $query->where($createdAtColumn, '>', $since))
            ->when($afterId !== null, fn (Builder $query) => $query->where($idColumn, '>', (int) $afterId));
    }
}
