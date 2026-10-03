<?php

namespace App\Http\Requests\Conversations;

use App\Enums\ModerationContext;
use App\Models\Conversation;
use App\Services\Ai\MessageModerator;
use App\Services\Ai\ModerationRecorder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Conversation $conversation */
        $conversation = $this->route('conversation');

        return $this->user()->can('sendMessage', $conversation);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * Employer messages must not ask about pregnancy or family plans; candidate messages are not moderated.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! $this->user()->isEmployer()) {
                    return;
                }

                $result = app(MessageModerator::class)->check((string) $this->input('body'));

                if (! $result->allowed) {
                    app(ModerationRecorder::class)->recordBlock(ModerationContext::ChatMessage, $result, (string) $this->input('body'), $this->user(), $this->route('conversation'));
                    $validator->errors()->add('body', (string) $result->reason);
                    $validator->errors()->add('body_suggestion', (string) $result->suggestion);
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Napisz wiadomość.',
            'body.max' => 'Wiadomość może mieć maksymalnie :max znaków.',
        ];
    }
}
