<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveLegalSourceRequest extends FormRequest
{
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
            'act' => ['required', 'string', 'max:255'],
            'article' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:10000'],
            'keywords' => ['array', 'max:30'],
            'keywords.*' => ['string', 'max:100'],
        ];
    }

    /**
     * Accept keywords as a list or a comma-separated string; trim them and drop empty and duplicate entries.
     */
    protected function prepareForValidation(): void
    {
        $keywords = $this->input('keywords');

        if (is_string($keywords)) {
            $keywords = explode(',', $keywords);
        }

        if (! is_array($keywords)) {
            $this->merge(['keywords' => []]);

            return;
        }

        $this->merge(['keywords' => collect($keywords)
            ->map(fn (mixed $keyword): mixed => is_string($keyword) ? trim($keyword) : $keyword)
            ->reject(fn (mixed $keyword): bool => $keyword === '' || $keyword === null)
            ->unique(fn (mixed $keyword): string => is_string($keyword) ? mb_strtolower($keyword) : (string) json_encode($keyword))
            ->values()
            ->all()]);
    }
}
