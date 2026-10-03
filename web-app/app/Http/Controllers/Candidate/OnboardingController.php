<?php

namespace App\Http\Controllers\Candidate;

use App\Enums\EmploymentFraction;
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
use App\Services\Candidate\ProfileOnboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    use ResolvesCandidateProfile;

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
    public function analyzeCv(AnalyzeCvRequest $request, ProfileOnboarding $onboarding): RedirectResponse
    {
        $analysis = $onboarding->analyzeCv(
            $this->candidateProfile($request),
            $request->hasFile('cv') ? $request->file('cv') : null,
            $request->input('cv_text'),
        );

        if ($analysis === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Nie udało się przeanalizować CV. Dodaj umiejętności ręcznie.']);

            return to_route('candidate.onboarding.show', ['step' => 2]);
        }

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
     *
     * @throws ValidationException
     */
    public function confirmSkills(Request $request, ProfileOnboarding $onboarding): RedirectResponse
    {
        $onboarding->confirmSkills($this->candidateProfile($request));

        return to_route('candidate.onboarding.show', ['step' => 3]);
    }

    /**
     * Step 2: the candidate reviews and edits the summary that employers see on her anonymous profile.
     */
    public function updateSummary(UpdateSummaryRequest $request, ProfileOnboarding $onboarding): RedirectResponse
    {
        $onboarding->updateSummary($this->candidateProfile($request), $request->input('ai_summary'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Opis dla pracodawców zapisany.']);

        return back();
    }

    /**
     * Step 3: work preferences and the private return calendar.
     */
    public function updatePreferences(UpdatePreferencesRequest $request, ProfileOnboarding $onboarding): RedirectResponse
    {
        $profile = $this->candidateProfile($request);

        $onboarding->updatePreferences($profile, $request->validated());

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
     *
     * @throws ValidationException
     */
    public function publish(UpdatePrivacyRequest $request, ProfileOnboarding $onboarding): RedirectResponse
    {
        $onboarding->publish($this->candidateProfile($request), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profil opublikowany. Firmy mogą Cię teraz zaprosić.']);

        return to_route('candidate.home');
    }

    /**
     * Hide a published profile from employers, or show it again.
     *
     * @throws ValidationException
     */
    public function toggleVisibility(Request $request, ProfileOnboarding $onboarding): RedirectResponse
    {
        $profile = $this->candidateProfile($request);
        $wasPublished = $profile->isPublished();

        $onboarding->setVisibility($profile, ! $wasPublished);

        Inertia::flash('toast', $wasPublished
            ? ['type' => 'info', 'message' => 'Profil ukryty. Pracodawcy go nie widzą.']
            : ['type' => 'success', 'message' => 'Profil znowu jest widoczny dla pracodawców.']);

        return back();
    }

    private function resolveStep(CandidateProfile $profile, int $requestedStep): int
    {
        $furthestReachable = $profile->isPublished() ? ProfileOnboarding::LAST_STEP : min($profile->onboarding_step + 1, ProfileOnboarding::LAST_STEP);

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
            'allow_direct_messages' => (bool) $profile->allow_direct_messages,
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
