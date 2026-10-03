<?php

namespace App\Http\Requests\Employer;

use App\Enums\CandidateDecisionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCandidateDecisionRequest extends FormRequest
{
    /**
     * Authorization is handled by JobOfferPolicy in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Invitations are created through the invitation endpoint, so only skip and save are accepted here.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in([CandidateDecisionType::Skipped->value, CandidateDecisionType::Saved->value])],
        ];
    }
}
