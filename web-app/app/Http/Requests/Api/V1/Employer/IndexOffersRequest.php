<?php

namespace App\Http\Requests\Api\V1\Employer;

use App\Enums\OfferStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexOffersRequest extends FormRequest
{
    /**
     * Authorization is handled by JobOfferPolicy in the controller.
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
            'status' => ['nullable', Rule::enum(OfferStatus::class)],
        ];
    }

    public function status(): ?OfferStatus
    {
        return OfferStatus::tryFrom((string) $this->validated('status'));
    }
}
