<?php

namespace App\Http\Requests\Admin;

use App\Enums\ArticleCategory;
use App\Models\Article;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveArticleRequest extends FormRequest
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
        $article = $this->route('article');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('articles', 'slug')->ignore($article instanceof Article ? $article->id : null),
            ],
            'category' => ['required', Rule::enum(ArticleCategory::class)],
            'excerpt' => ['required', 'string', 'max:1000'],
            'body' => ['required', 'string', 'max:100000'],
            'reading_minutes' => ['nullable', 'integer', 'min:1', 'max:120'],
            'is_featured' => ['boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Adres może zawierać tylko małe litery, cyfry i myślniki.',
            'slug.unique' => 'Artykuł z takim adresem już istnieje.',
        ];
    }

    /**
     * Derive the slug from the title when it was left empty and normalise a typed one.
     */
    protected function prepareForValidation(): void
    {
        $slug = trim((string) $this->input('slug'));
        $source = $slug !== '' ? $slug : (string) $this->input('title');

        $this->merge([
            'slug' => Str::slug($source),
            'is_featured' => $this->boolean('is_featured'),
            'reading_minutes' => $this->filled('reading_minutes') ? $this->input('reading_minutes') : null,
            'published_at' => $this->filled('published_at') ? $this->input('published_at') : null,
        ]);
    }
}
