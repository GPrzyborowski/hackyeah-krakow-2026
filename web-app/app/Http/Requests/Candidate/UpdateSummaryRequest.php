<?php

namespace App\Http\Requests\Candidate;

use App\Rules\NoContactDetails;
use App\Services\Ai\MessageModerator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The short "about me" shown to employers on the anonymous profile.
 */
class UpdateSummaryRequest extends FormRequest
{
    public const int MAX_LENGTH = 400;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ai_summary' => ['nullable', 'string', 'max:'.self::MAX_LENGTH, new NoContactDetails],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $summary = $this->input('ai_summary');

                if ($validator->errors()->has('ai_summary') || ! is_string($summary) || trim($summary) === '') {
                    return;
                }

                if (! app(MessageModerator::class)->check($summary)->allowed) {
                    $validator->errors()->add('ai_summary', 'Nie wspominaj o ciąży, dzieciach ani planach rodzinnych – to informacje tylko dla Ciebie.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ai_summary.max' => 'Opis może mieć maksymalnie :max znaków.',
        ];
    }
}
