<?php

namespace App\Http\Requests\Api\V1\Candidate;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Hide the published profile from employers (visible=false) or show it again (visible=true).
 */
class UpdateVisibilityRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'visible' => ['required', 'boolean'],
        ];
    }
}
