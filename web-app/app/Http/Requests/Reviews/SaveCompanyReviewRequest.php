<?php

namespace App\Http\Requests\Reviews;

use App\Rules\NoContactDetails;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveCompanyReviewRequest extends FormRequest
{
    /**
     * Authorization happens in the controller (policy with the route's company or review).
     */
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
            'rating_return' => ['required', 'integer', 'between:1,5'],
            'rating_flexibility' => ['required', 'integer', 'between:1,5'],
            'rating_no_pregnancy_questions' => ['required', 'integer', 'between:1,5'],
            'quote' => ['required', 'string', 'min:10', 'max:300', $this->withoutPersonalData()],
            'author_label' => ['nullable', 'string', 'max:80', $this->withoutPersonalData()],
        ];
    }

    /**
     * Reviews are published anonymously, so they must not contain e-mail addresses or phone numbers.
     */
    private function withoutPersonalData(): NoContactDetails
    {
        return new NoContactDetails('Usuń adres e-mail lub numer telefonu – opinie publikujemy anonimowo.');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating_return.required' => 'Oceń powrót po urlopie.',
            'rating_flexibility.required' => 'Oceń elastyczność godzin.',
            'rating_no_pregnancy_questions.required' => 'Oceń, czy rozmowy były bez pytań o ciążę.',
            'rating_return.between' => 'Wybierz od 1 do 5 gwiazdek.',
            'rating_flexibility.between' => 'Wybierz od 1 do 5 gwiazdek.',
            'rating_no_pregnancy_questions.between' => 'Wybierz od 1 do 5 gwiazdek.',
            'quote.required' => 'Napisz kilka słów o firmie.',
            'quote.min' => 'Napisz co najmniej 10 znaków.',
            'quote.max' => 'Opinia może mieć najwyżej 300 znaków.',
            'author_label.max' => 'Podpis może mieć najwyżej 80 znaków.',
        ];
    }
}
