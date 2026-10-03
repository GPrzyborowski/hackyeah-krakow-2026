<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePrivacyRequest extends FormRequest
{
    /**
     * Polish number: optional +48 prefix and 9 digits, spaces or dashes allowed between digits.
     */
    public const string PHONE_PATTERN = '/^(?:\+48[\s-]?)?(?:\d[\s-]?){8}\d$/';

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
            'job_alerts_enabled' => ['sometimes', 'boolean'],
            'hidden_from_company_id' => ['sometimes', 'nullable', 'integer', 'exists:companies,id'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:'.self::PHONE_PATTERN],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Podaj polski numer telefonu: 9 cyfr, opcjonalnie z +48.',
            'phone.max' => 'Podaj polski numer telefonu: 9 cyfr, opcjonalnie z +48.',
        ];
    }

    /**
     * Store valid numbers in one readable shape ("+48 600 100 200"); an empty value clears the phone.
     */
    protected function prepareForValidation(): void
    {
        if (! is_string($this->input('phone'))) {
            return;
        }

        $phone = trim($this->input('phone'));

        if ($phone === '') {
            $this->merge(['phone' => null]);

            return;
        }

        if (preg_match(self::PHONE_PATTERN, $phone) === 1) {
            $digits = substr((string) preg_replace('/\D/', '', $phone), -9);
            $this->merge(['phone' => '+48 '.implode(' ', str_split($digits, 3))]);
        }
    }
}
