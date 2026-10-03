<?php

namespace App\Http\Requests\Employer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PreviewMatchesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->company_id !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'required_skills' => ['present', 'array', 'max:20'],
            'required_skills.*' => ['required', 'string', 'max:80'],
            'nice_to_have_skills' => ['present', 'array', 'max:20'],
            'nice_to_have_skills.*' => ['required', 'string', 'max:80'],
        ];
    }
}
