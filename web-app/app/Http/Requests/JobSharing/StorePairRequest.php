<?php

namespace App\Http\Requests\JobSharing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePairRequest extends FormRequest
{
    /**
     * Eligibility of the partner is checked by the controller with PartnerFinder.
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
            'partner_id' => ['required', 'integer', 'exists:candidate_profiles,id'],
        ];
    }
}
