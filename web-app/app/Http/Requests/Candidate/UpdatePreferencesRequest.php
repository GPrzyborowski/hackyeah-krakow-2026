<?php

namespace App\Http\Requests\Candidate;

use App\Enums\EmploymentFraction;
use App\Enums\WorkMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreferencesRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'headline' => ['nullable', 'string', 'max:120'],
            'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:50'],
            'city' => ['nullable', 'string', 'max:100'],
            'work_modes' => ['array'],
            'work_modes.*' => [Rule::enum(WorkMode::class)],
            'employment_fractions' => ['array'],
            'employment_fractions.*' => [Rule::enum(EmploymentFraction::class)],
            'wants_flexible_hours' => ['boolean'],
            'open_to_job_sharing' => ['boolean'],
            'available_from' => ['required', 'date'],
            'leave_starts_on' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'available_from.required' => 'Podaj, od kiedy możesz zacząć pracę.',
        ];
    }
}
