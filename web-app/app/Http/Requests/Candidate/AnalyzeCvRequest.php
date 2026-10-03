<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AnalyzeCvRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cv' => ['nullable', 'required_without:cv_text', 'file', 'mimes:pdf', 'max:5120'],
            'cv_text' => ['nullable', 'required_without:cv', 'string', 'max:20000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cv.required_without' => 'Dodaj plik PDF z CV albo wklej jego treść.',
            'cv_text.required_without' => 'Dodaj plik PDF z CV albo wklej jego treść.',
            'cv.mimes' => 'CV musi być plikiem PDF.',
            'cv.max' => 'Plik CV może mieć najwyżej 5 MB.',
        ];
    }
}
