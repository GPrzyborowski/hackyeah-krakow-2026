<?php

namespace App\Http\Requests\Employer;

use App\Enums\EmploymentFraction;
use App\Enums\WorkMode;
use App\Services\Ai\MessageModerator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveJobOfferRequest extends FormRequest
{
    public const string ACTION_DRAFT = 'draft';

    public const string ACTION_PUBLISH = 'publish';

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
        $isPublishing = $this->isPublishing();

        return [
            'action' => ['required', Rule::in([self::ACTION_DRAFT, self::ACTION_PUBLISH])],
            'title' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'work_mode' => ['required', Rule::enum(WorkMode::class)],
            'start_date' => ['required', 'date', ...($isPublishing ? ['after_or_equal:today'] : [])],
            'description' => ['nullable', 'string', 'max:5000'],
            'employment_fraction' => ['required', Rule::enum(EmploymentFraction::class)],
            'salary_min' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'salary_max' => ['nullable', 'integer', 'min:0', 'max:1000000', ...($this->filled('salary_min') ? ['gte:salary_min'] : [])],
            'flexible_hours' => ['boolean'],
            'fixed_meeting_hours' => ['boolean'],
            'childcare_subsidy' => ['boolean'],
            'required_skills' => [$isPublishing ? 'required' : 'present', 'array', 'max:20'],
            'required_skills.*' => ['required', 'string', 'max:80'],
            'nice_to_have_skills' => ['present', 'array', 'max:20'],
            'nice_to_have_skills.*' => ['required', 'string', 'max:80'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required_skills.required' => 'Dodaj co najmniej jedną wymaganą umiejętność, aby opublikować ofertę.',
            'salary_max.gte' => 'Górna granica wynagrodzenia nie może być niższa od dolnej.',
            'start_date.after_or_equal' => 'Planowany start nie może być w przeszłości.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'nazwa stanowiska',
            'city' => 'miasto',
            'work_mode' => 'tryb pracy',
            'start_date' => 'planowany start',
            'description' => 'opis stanowiska',
            'employment_fraction' => 'wymiar etatu',
            'salary_min' => 'wynagrodzenie od',
            'salary_max' => 'wynagrodzenie do',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $description = $this->input('description');

                if (! is_string($description) || trim($description) === '') {
                    return;
                }

                $result = app(MessageModerator::class)->check($description);

                if (! $result->allowed) {
                    $validator->errors()->add('description', trim($result->reason.' '.$result->suggestion));
                }
            },
        ];
    }

    public function isPublishing(): bool
    {
        return $this->input('action') === self::ACTION_PUBLISH;
    }

    /**
     * @return list<string>
     */
    public function requiredSkillNames(): array
    {
        return $this->skillNames('required_skills');
    }

    /**
     * @return list<string>
     */
    public function niceToHaveSkillNames(): array
    {
        return $this->skillNames('nice_to_have_skills');
    }

    /**
     * @return list<string>
     */
    private function skillNames(string $key): array
    {
        return array_values($this->safe()->collect($key)
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique(fn (string $name): string => mb_strtolower($name))
            ->all());
    }
}
