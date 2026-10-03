<?php

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Enums\DayPart;
use App\Enums\EmploymentFraction;
use App\Enums\WorkMode;
use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Candidate\UpdateVisibilityRequest;
use App\Http\Requests\Candidate\AnalyzeCvRequest;
use App\Http\Requests\Candidate\UpdatePreferencesRequest;
use App\Http\Requests\Candidate\UpdatePrivacyRequest;
use App\Http\Requests\Candidate\UpdateSummaryRequest;
use App\Http\Resources\AnonymousCandidateResource;
use App\Http\Resources\Api\V1\CandidateProfileResource;
use App\Models\Company;
use App\Models\Skill;
use App\Services\Candidate\ProfileOnboarding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The candidate's own profile and the onboarding wizard steps (CV, skills, preferences, privacy, publishing).
 */
class ProfileController extends Controller
{
    use ResolvesCandidateProfile;

    public function show(Request $request): CandidateProfileResource
    {
        return new CandidateProfileResource($this->candidateProfile($request));
    }

    /**
     * Choice lists for the profile forms.
     */
    public function options(): JsonResponse
    {
        return response()->json(['data' => [
            'work_modes' => collect(WorkMode::cases())->map(fn (WorkMode $mode): array => ['value' => $mode->value, 'label' => $mode->label()]),
            'employment_fractions' => collect(EmploymentFraction::cases())->map(fn (EmploymentFraction $fraction): array => ['value' => $fraction->value, 'label' => $fraction->label()]),
            'day_parts' => collect(DayPart::cases())->map(fn (DayPart $part): array => ['value' => $part->value, 'label' => $part->label()]),
            'companies' => Company::query()->orderBy('name')->get(['id', 'name'])->map(fn (Company $company): array => ['id' => $company->id, 'name' => $company->name]),
            'skill_suggestions' => Skill::query()->suggestable()->orderBy('name')->pluck('name'),
        ]]);
    }

    /**
     * Upload a CV (PDF) and/or paste its text; the analyzer proposes unconfirmed skills, positions and a summary.
     *
     * @throws ValidationException
     */
    public function analyzeCv(AnalyzeCvRequest $request, ProfileOnboarding $onboarding): CandidateProfileResource
    {
        $profile = $this->candidateProfile($request);
        $analysis = $onboarding->analyzeCv(
            $profile,
            $request->hasFile('cv') ? $request->file('cv') : null,
            $request->input('cv_text'),
        );

        $message = match (true) {
            $analysis === null => 'Nie udało się przeanalizować CV. Dodaj umiejętności ręcznie.',
            $analysis->skills === [] => 'CV zapisane. Nie znaleźliśmy umiejętności – dodaj je ręcznie.',
            default => 'Przeczytaliśmy Twoje CV. Sprawdź proponowane tagi.',
        };

        return (new CandidateProfileResource($profile->refresh()))->additional(['meta' => [
            'analysis_succeeded' => $analysis !== null,
            'message' => $message,
        ]]);
    }

    /**
     * Confirm every remaining (AI-proposed) tag; confirmed skills become visible to employers.
     *
     * @throws ValidationException
     */
    public function confirmSkills(Request $request, ProfileOnboarding $onboarding): CandidateProfileResource
    {
        $profile = $this->candidateProfile($request);
        $onboarding->confirmSkills($profile);

        return new CandidateProfileResource($profile->refresh());
    }

    public function updatePreferences(UpdatePreferencesRequest $request, ProfileOnboarding $onboarding): CandidateProfileResource
    {
        $profile = $this->candidateProfile($request);
        $onboarding->updatePreferences($profile, $request->validated());

        return new CandidateProfileResource($profile->refresh());
    }

    /**
     * Partial update of the privacy toggles.
     */
    public function updatePrivacy(UpdatePrivacyRequest $request): CandidateProfileResource
    {
        $profile = $this->candidateProfile($request);
        $profile->update($request->validated());

        return new CandidateProfileResource($profile->refresh());
    }

    /**
     * The "about me" text employers see; contact details and family topics are rejected.
     */
    public function updateSummary(UpdateSummaryRequest $request, ProfileOnboarding $onboarding): CandidateProfileResource
    {
        $profile = $this->candidateProfile($request);
        $onboarding->updateSummary($profile, $request->input('ai_summary'));

        return new CandidateProfileResource($profile->refresh());
    }

    /**
     * Make the profile visible to employers (requires available_from and at least one confirmed skill).
     *
     * @throws ValidationException
     */
    public function publish(UpdatePrivacyRequest $request, ProfileOnboarding $onboarding): CandidateProfileResource
    {
        $profile = $this->candidateProfile($request);
        $onboarding->publish($profile, $request->validated());

        return new CandidateProfileResource($profile->refresh());
    }

    /**
     * Hide the profile from employers or show it again.
     *
     * @throws ValidationException
     */
    public function updateVisibility(UpdateVisibilityRequest $request, ProfileOnboarding $onboarding): CandidateProfileResource
    {
        $profile = $this->candidateProfile($request);
        $onboarding->setVisibility($profile, $request->boolean('visible'));

        return new CandidateProfileResource($profile->refresh());
    }

    /**
     * Exactly what employers see before an invitation is accepted.
     */
    public function employerPreview(Request $request): AnonymousCandidateResource
    {
        return new AnonymousCandidateResource($this->candidateProfile($request)->load(['user', 'confirmedSkills']));
    }
}
