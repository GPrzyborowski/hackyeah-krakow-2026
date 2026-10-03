<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProfilePhotoRequest extends FormRequest
{
    public const int MAX_KILOBYTES = 3072;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_KILOBYTES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'Wybierz zdjęcie.',
            'photo.image' => 'Zdjęcie musi być plikiem JPG, PNG lub WebP.',
            'photo.mimes' => 'Zdjęcie musi być plikiem JPG, PNG lub WebP.',
            'photo.max' => 'Zdjęcie może mieć najwyżej 3 MB.',
        ];
    }
}
