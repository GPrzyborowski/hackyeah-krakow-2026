<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePrivacyRequest extends FormRequest
{
    /**
     * Partial updates are allowed so single toggles can autosave.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'show_availability_instead_of_gap' => ['sometimes', 'boolean'],
            'allow_direct_messages' => ['sometimes', 'boolean'],
            'hidden_from_company_id' => ['sometimes', 'nullable', 'integer', 'exists:companies,id'],
        ];
    }
}
