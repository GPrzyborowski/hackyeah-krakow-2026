<?php

namespace App\Http\Requests\JobSharing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePairScheduleRequest extends FormRequest
{
    /**
     * Membership is checked by JobSharePairPolicy in the controller.
     */
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
            'schedule' => ['required', 'array', 'min:1', 'max:2'],
            'schedule.*.candidate_profile_id' => ['required', 'integer', 'distinct'],
            'schedule.*.starts_at' => ['required', 'date_format:H:i'],
            'schedule.*.ends_at' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'schedule.*.starts_at.date_format' => 'Podaj godzinę w formacie GG:MM.',
            'schedule.*.ends_at.date_format' => 'Podaj godzinę w formacie GG:MM.',
        ];
    }

    /**
     * @return list<array{candidate_profile_id: int, starts_at: string, ends_at: string}>
     */
    public function blocks(): array
    {
        return array_values($this->safe()->collect('schedule')
            ->map(fn (array $block): array => [
                'candidate_profile_id' => (int) $block['candidate_profile_id'],
                'starts_at' => (string) $block['starts_at'],
                'ends_at' => (string) $block['ends_at'],
            ])
            ->all());
    }
}
