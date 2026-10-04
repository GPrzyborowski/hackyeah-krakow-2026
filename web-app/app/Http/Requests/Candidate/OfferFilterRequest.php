<?php

namespace App\Http\Requests\Candidate;

use App\Enums\ContractType;
use App\Enums\EmploymentFraction;
use App\Enums\OfferCategory;
use App\Enums\WorkMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OfferFilterRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:100'],
            'categories' => ['nullable', 'array'],
            'categories.*' => [Rule::enum(OfferCategory::class)],
            'work_modes' => ['nullable', 'array'],
            'work_modes.*' => [Rule::enum(WorkMode::class)],
            'employment_fractions' => ['nullable', 'array'],
            'employment_fractions.*' => [Rule::enum(EmploymentFraction::class)],
            'contract_types' => ['nullable', 'array'],
            'contract_types.*' => [Rule::enum(ContractType::class)],
            'flexible_hours' => ['nullable', 'boolean'],
            'childcare_subsidy' => ['nullable', 'boolean'],
            'nursery_nearby' => ['nullable', 'boolean'],
            'with_reviews' => ['nullable', 'boolean'],
            'verified_only' => ['nullable', 'boolean'],
            'job_share' => ['nullable', 'boolean'],
            'saved' => ['nullable', 'boolean'],
            'start_from' => ['nullable', 'date'],
            'sort' => ['nullable', Rule::in(['match', 'newest'])],
        ];
    }
}
