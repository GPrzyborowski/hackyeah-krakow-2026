<?php

namespace App\Http\Requests\Candidate;

use App\Rules\NoContactDetails;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProfileSkillRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:60',
                new NoContactDetails('Nazwa umiejętności nie może zawierać e-maila ani numeru telefonu – pracodawca widzi ją na anonimowym profilu.'),
            ],
        ];
    }
}
