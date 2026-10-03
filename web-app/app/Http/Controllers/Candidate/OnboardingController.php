<?php

namespace App\Http\Controllers\Candidate;

use App\Enums\CvStatus;
use App\Enums\EmploymentFraction;
use App\Enums\SkillSource;
use App\Enums\WorkMode;
use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\AnalyzeCvRequest;
use App\Http\Requests\Candidate\UpdatePreferencesRequest;
use App\Http\Requests\Candidate\UpdatePrivacyRequest;
use App\Http\Requests\Candidate\UpdateSummaryRequest;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Skill;
use App\Services\Ai\CvAnalysis;
use App\Services\Ai\CvAnalyzer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class OnboardingController extends Controller
{
    use ResolvesCandidateProfile;

    private const int LAST_STEP = 4;

    /**
     * Show the 4-step profile wizard (also used as the profile editor once published).
     */
    public function show(Request $request): Response
    {
        $profile = $this->candidateProfile($request)->load(['user', 'skills']);

        return Inertia::render('candidate/Onboarding', [
            'step' => $this->resolveStep($profile, $request->integer('step')),
            'profile' => $this->presentProfile($profile),
            'skills' => $profile->skills
                ->sortBy('name')
                ->map(fn (Skill $skill): array => [
                    'id' => $skill->id,
                    'name' => $skill->name,
                    'source' => $skill->getRelationValue('pivot')?->source,
                    'confirmed' => $skill->getRelationValue('pivot')?->confirmed_at !== null,
                ])
                ->values(),
            'skillSuggestions' => Skill::query()->orderBy('name')->pluck('name'),
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
            'workModes' => collect(WorkMode::cases())->map(fn (WorkMode $mode): array => ['value' => $mode->value, 'label' => $mode->label()]),
            'employmentFractions' => collect(EmploymentFraction::cases())->map(fn (EmploymentFraction $fraction): array => ['value' => $fraction->value, 'label' => $fraction->label()]),
        ]);
    }

    /**
     * Step 2: store the CV (file and/or pasted text) and let the analyzer propose skills and positions.
     *
     * @throws ValidationException
     */
    public function analyzeCv(AnalyzeCvRequest $request, CvAnalyzer $analyzer): RedirectResponse
    {
        $profile = $this->candidateProfile($request);

        if ($request->hasFile('cv')) {
            $storedPath = $request->file('cv')->store('cvs', 'local');

            if ($storedPath === false) {
                throw ValidationException::withMessages(['cv' => 'Nie udało się zapisać pliku CV. Spróbuj ponownie.']);
            }

            if ($profile->cv_path) {
                Storage::disk('local')->delete($profile->cv_path);
            }

            $profile->cv_path = $storedPath;
            $profile->cv_original_name = $request->file('cv')->getClientOriginalName();
        }

        if ($request->filled('cv_text')) {
            $profile->cv_text = $request->string('cv_text')->trim()->toString();
        }

        $profile->cv_status = CvStatus::Parsing;
        $profile->save();

        try {
            $analysis = $analyzer->analyze($profile);
        } catch (Throwable $exception) {
            report($exception);
            $profile->update(['cv_status' => CvStatus::Failed]);
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Nie udało się przeanalizować CV. Dodaj umiejętności ręcznie.']);

            return to_route('candidate.onboarding.show', ['step' => 2]);
        }

        $this->applyAnalysis($profile, $analysis);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $analysis->skills === []
                ? 'CV zapisane. Nie znaleźliśmy umiejętności – dodaj je ręcznie.'
                : 'Przeczytaliśmy Twoje CV. Sprawdź proponowane tagi.',
        ]);

        return to_route('candidate.onboarding.show', ['step' => 2]);
    }

    /**
     * Step 2 done: every remaining tag becomes confirmed and visible to employers.
     */
    public function confirmSkills(Request $request): RedirectResponse
    {
        $profile = $this->candidateProfile($request);

        if (! $profile->skills()->exists()) {
            throw ValidationException::withMessages(['skills' => 'Dodaj przynajmniej jedną umiejętność.']);
        }

        $unconfirmedSkillIds = $profile->skills()->wherePivotNull('confirmed_at')->pluck('skills.id')->all();

        if ($unconfirmedSkillIds !== []) {
            $profile->skills()->updateExistingPivot($unconfirmedSkillIds, ['confirmed_at' => now()]);
        }

        $this->advanceTo($profile, 2);

        return to_route('candidate.onboarding.show', ['step' => 3]);
    }

    /**
     * Step 2: the candidate reviews and edits the summary that employers see on her anonymous profile.
     */
    public function updateSummary(UpdateSummaryRequest $request): RedirectResponse
    {
        $summary = $request->string('ai_summary')->trim()->toString();

        $this->candidateProfile($request)->update(['ai_summary' => $summary === '' ? null : $summary]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Opis dla pracodawców zapisany.']);

        return back();
    }

    /**
     * Step 3: work preferences and the private return calendar.
     */
    public function updatePreferences(UpdatePreferencesRequest $request): RedirectResponse
    {
        $profile = $this->candidateProfile($request);

        $profile->fill([
            ...$request->validated(),
            'work_modes' => $request->validated('work_modes', []),
            'employment_fractions' => $request->validated('employment_fractions', []),
        ]);
        $this->advanceTo($profile, 3);

        if ($profile->isPublished()) {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Preferencje zapisane.']);

            return to_route('candidate.onboarding.show', ['step' => 3]);
        }

        return to_route('candidate.onboarding.show', ['step' => 4]);
    }

    /**
     * Autosave of the privacy toggles.
     */
    public function updatePrivacy(UpdatePrivacyRequest $request): RedirectResponse
    {
        $this->candidateProfile($request)->update($request->validated());

        return back();
    }

    /**
     * Step 4: make the profile visible to employers.
     */
    public function publish(UpdatePrivacyRequest $request): RedirectResponse
    {
        $profile = $this->candidateProfile($request);
        $profile->fill($request->validated());

        $this->ensurePublishable($profile);

        $profile->published_at ??= now();
        $this->advanceTo($profile, self::LAST_STEP);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profil opublikowany. Firmy mogą Cię teraz zaprosić.']);

        return to_route('candidate.home');
    }

    /**
     * Hide a published profile from employers, or show it again.
     */
    public function toggleVisibility(Request $request): RedirectResponse
    {
        $profile = $this->candidateProfile($request);

        if ($profile->isPublished()) {
            $profile->update(['published_at' => null]);
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Profil ukryty. Pracodawcy go nie widzą.']);
        } else {
            $this->ensurePublishable($profile);
            $profile->update(['published_at' => now(), 'onboarding_step' => self::LAST_STEP]);
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Profil znowu jest widoczny dla pracodawców.']);
        }

        return back();
    }

    private function applyAnalysis(CandidateProfile $profile, CvAnalysis $analysis): void
    {
        DB::transaction(function () use ($profile, $analysis): void {
            $attachedSkillIds = $profile->skills()->pluck('skills.id');

            foreach ($analysis->skills as $skillName) {
                if (blank($skillName)) {
                    continue;
                }

                $skill = Skill::findOrCreateByName($skillName);

                if (! $attachedSkillIds->contains($skill->id)) {
                    $profile->skills()->attach($skill->id, ['source' => SkillSource::Ai->value, 'confirmed_at' => null]);
                    $attachedSkillIds->push($skill->id);
                }
            }

            $profile->suggested_positions = $analysis->positions;
            $profile->ai_summary = $analysis->summary !== null ? Str::limit($analysis->summary, UpdateSummaryRequest::MAX_LENGTH - 1, '…') : $profile->ai_summary;
            $profile->headline = filled($profile->headline) ? $profile->headline : $analysis->headline;
            $profile->years_of_experience ??= $analysis->yearsOfExperience;

            if (filled($analysis->extractedText) && blank($profile->cv_text)) {
                $profile->cv_text = $analysis->extractedText;
            }

            $profile->cv_status = CvStatus::Parsed;
            $profile->save();
        });
    }

    /**
     * @throws ValidationException
     */
    private function ensurePublishable(CandidateProfile $profile): void
    {
        $errors = [];

        if ($profile->available_from === null) {
            $errors['available_from'] = 'Uzupełnij datę „Od kiedy możesz zacząć?” w kroku 3.';
        }

        if (! $profile->confirmedSkills()->exists()) {
            $errors['skills'] = 'Zatwierdź przynajmniej jedną umiejętność w kroku 2.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function advanceTo(CandidateProfile $profile, int $completedStep): void
    {
        $profile->onboarding_step = max($profile->onboarding_step, $completedStep);
        $profile->save();
    }

    private function resolveStep(CandidateProfile $profile, int $requestedStep): int
    {
        $furthestReachable = $profile->isPublished() ? self::LAST_STEP : min($profile->onboarding_step + 1, self::LAST_STEP);

        if ($requestedStep === 0) {
            return $profile->isPublished() ? 2 : $furthestReachable;
        }

        return max(2, min($requestedStep, $furthestReachable));
    }

    /**
     * @return array<string, mixed>
     */
    private function presentProfile(CandidateProfile $profile): array
    {
        $cvExists = $profile->cv_path && Storage::disk('local')->exists($profile->cv_path);

        return [
            'anonymous_name' => $profile->anonymousName(),
            'headline' => $profile->headline,
            'years_of_experience' => $profile->years_of_experience,
            'city' => $profile->city,
            'ai_summary' => $profile->ai_summary,
            'available_from' => $profile->available_from?->toDateString(),
            'leave_starts_on' => $profile->leave_starts_on?->toDateString(),
            'due_date' => $profile->due_date?->toDateString(),
            'work_modes' => $profile->work_modes ?? [],
            'employment_fractions' => $profile->employment_fractions ?? [],
            'wants_flexible_hours' => (bool) $profile->wants_flexible_hours,
            'open_to_job_sharing' => (bool) $profile->open_to_job_sharing,
            'preferred_day_part' => $profile->preferred_day_part?->value,
            'hidden_from_company_id' => $profile->hidden_from_company_id,
            'onboarding_step' => $profile->onboarding_step,
            'cv_original_name' => $profile->cv_original_name,
            'cv_size' => $cvExists ? Storage::disk('local')->size($profile->cv_path) : null,
            'cv_status' => $profile->cv_status?->value,
            'cv_text' => $profile->cv_text,
            'suggested_positions' => $profile->suggested_positions ?? [],
            'is_published' => $profile->isPublished(),
        ];
    }
}
