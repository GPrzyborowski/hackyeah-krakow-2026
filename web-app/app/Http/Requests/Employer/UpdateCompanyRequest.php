<?php

namespace App\Http\Requests\Employer;

use App\Services\Ai\MessageModerator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCompanyRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'nip' => ['nullable', 'string', 'regex:/^\d{10}$/'],
            'city' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nip.regex' => 'NIP musi składać się z 10 cyfr.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $description = $this->input('description');

                if (! is_string($description) || trim($description) === '' || $validator->errors()->has('description')) {
                    return;
                }

                $result = app(MessageModerator::class)->check($description);

                if (! $result->allowed) {
                    $validator->errors()->add('description', trim($result->reason.' '.$result->suggestion));
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nip'))) {
            $this->merge(['nip' => preg_replace('/[\s-]/', '', $this->input('nip')) ?: null]);
        }
    }
}
