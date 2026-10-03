<?php

namespace App\Http\Requests\Employer;

use App\Enums\EmploymentFraction;
use App\Enums\ModerationContext;
use App\Enums\WorkMode;
use App\Services\Ai\MessageModerator;
use App\Services\Ai\ModerationRecorder;
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
            'nursery_distance_km' => ['nullable', 'integer', 'min:0', 'max:50'],
            'is_job_share' => ['boolean'],
            'workday_starts_at' => ['nullable', 'required_if_accepted:is_job_share', 'date_format:H:i'],
            'workday_ends_at' => ['nullable', 'required_if_accepted:is_job_share', 'date_format:H:i', ...($this->filled('workday_starts_at') ? ['after:workday_starts_at'] : [])],
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
            'workday_starts_at.required_if_accepted' => 'Podaj, od której godziny trwa dzień pracy na tym stanowisku.',
            'workday_ends_at.required_if_accepted' => 'Podaj, do której godziny trwa dzień pracy na tym stanowisku.',
            'workday_ends_at.after' => 'Koniec dnia pracy musi być później niż początek.',
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
            'nursery_distance_km' => 'odległość do żłobka lub przedszkola',
            'workday_starts_at' => 'początek dnia pracy',
            'workday_ends_at' => 'koniec dnia pracy',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $moderator = app(MessageModerator::class);

                foreach (['title', 'description'] as $field) {
                    $text = $this->input($field);

                    if (! is_string($text) || trim($text) === '' || $validator->errors()->has($field)) {
                        continue;
                    }

                    $result = $moderator->check($text);

                    if (! $result->allowed) {
                        app(ModerationRecorder::class)->recordBlock(ModerationContext::Offer, $result, $text, $this->user(), $this->route('offer'));
                        $validator->errors()->add($field, trim($result->reason.' '.$result->suggestion));
                    }
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
