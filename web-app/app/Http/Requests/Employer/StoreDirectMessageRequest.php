<?php

namespace App\Http\Requests\Employer;

use App\Enums\ModerationContext;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A short question to a candidate who allows direct messages; moderated exactly like an invitation message.
 */
class StoreDirectMessageRequest extends StoreInvitationRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:1000'],
        ];
    }

    protected function moderationContext(): ModerationContext
    {
        return ModerationContext::DirectMessage;
    }
}
