<?php

namespace App\Http\Requests\Api\V1\Employer;

use App\Enums\InvitationStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexInvitationsRequest extends FormRequest
{
    /**
     * Any employer may list invitations; the controller scopes them to her company.
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
            'status' => ['nullable', Rule::enum(InvitationStatus::class)],
        ];
    }

    public function status(): ?InvitationStatus
    {
        return InvitationStatus::tryFrom((string) $this->validated('status'));
    }
}
