<?php

namespace App\Http\Requests\Employer;

use App\Enums\ModerationContext;
use App\Services\Ai\MessageModerator;
use App\Services\Ai\ModerationRecorder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInvitationRequest extends FormRequest
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
            'message' => ['required', 'string', 'max:2000'],
            'from' => ['nullable', 'string', 'in:invitations'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'message' => 'wiadomość',
        ];
    }

    /**
     * Employer-authored text must not ask about pregnancy or family plans.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('message')) {
                    return;
                }

                $result = app(MessageModerator::class)->check((string) $this->input('message'));

                if (! $result->allowed) {
                    app(ModerationRecorder::class)->recordBlock($this->moderationContext(), $result, (string) $this->input('message'), $this->user(), $this->route('offer') ?? $this->route('pair'));
                    $validator->errors()->add('message', (string) $result->reason);
                    $validator->errors()->add('message_suggestion', (string) $result->suggestion);
                }
            },
        ];
    }

    /**
     * Where the blocked text is logged in the moderation log.
     */
    protected function moderationContext(): ModerationContext
    {
        return ModerationContext::Invitation;
    }
}
