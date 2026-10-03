<?php

namespace App\Services\Candidate;

use App\Enums\CvStatus;
use App\Enums\SkillSource;
use App\Http\Requests\Candidate\UpdateSummaryRequest;
use App\Models\CandidateProfile;
use App\Models\Skill;
use App\Services\Ai\CvAnalysis;
use App\Services\Ai\CvAnalyzer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Candidate profile wizard operations shared by the web onboarding and the mobile API.
 */
class ProfileOnboarding
{
    public const int LAST_STEP = 4;

    public function __construct(private readonly CvAnalyzer $analyzer) {}

    /**
     * Store the CV (file and/or pasted text) and let the analyzer propose skills and positions.
     * Returns null when the analysis failed (the profile is then marked as failed and the error reported).
     *
     * @throws ValidationException
     */
    public function analyzeCv(CandidateProfile $profile, ?UploadedFile $cvFile, ?string $cvText): ?CvAnalysis
    {
        if ($cvFile !== null) {
            $storedPath = $cvFile->store('cvs', 'local');

            if ($storedPath === false) {
                throw ValidationException::withMessages(['cv' => 'Nie udało się zapisać pliku CV. Spróbuj ponownie.']);
            }

            if ($profile->cv_path) {
                Storage::disk('local')->delete($profile->cv_path);
            }

            $profile->cv_path = $storedPath;
            $profile->cv_original_name = $cvFile->getClientOriginalName();
        }

        if (filled($cvText)) {
            $profile->cv_text = trim($cvText);
        }

        $profile->cv_status = CvStatus::Parsing;
        $profile->save();

        try {
            $analysis = $this->analyzer->analyze($profile);
        } catch (Throwable $exception) {
            report($exception);
            $profile->update(['cv_status' => CvStatus::Failed]);

            return null;
        }

        $this->applyAnalysis($profile, $analysis);

        return $analysis;
    }

    /**
     * Add a tag typed by the candidate (existing dictionary skill or a new one); she typed it herself, so it is confirmed at once.
     */
    public function addManualSkill(CandidateProfile $profile, string $name): Skill
    {
        $skill = Skill::findOrCreateByName($name);

        if (! $profile->skills()->whereKey($skill->id)->exists()) {
            $profile->skills()->attach($skill->id, ['source' => SkillSource::Manual->value, 'confirmed_at' => now()]);
        }

        return $skill;
    }

    /**
     * Step 2 done: every remaining tag becomes confirmed and visible to employers.
     *
     * @throws ValidationException
     */
    public function confirmSkills(CandidateProfile $profile): void
    {
        if (! $profile->skills()->exists()) {
            throw ValidationException::withMessages(['skills' => 'Dodaj przynajmniej jedną umiejętność.']);
        }

        $unconfirmedSkillIds = $profile->skills()->wherePivotNull('confirmed_at')->pluck('skills.id')->all();

        if ($unconfirmedSkillIds !== []) {
            $profile->skills()->updateExistingPivot($unconfirmedSkillIds, ['confirmed_at' => now()]);
        }

        $this->advanceTo($profile, 2);
    }

    /**
     * Save the summary employers see on the anonymous profile; an empty one is cleared.
     */
    public function updateSummary(CandidateProfile $profile, ?string $summary): void
    {
        $summary = trim((string) $summary);

        $profile->update(['ai_summary' => $summary === '' ? null : $summary]);
    }

    /**
     * Step 3: work preferences and the private return calendar.
     *
     * @param  array<string, mixed>  $preferences
     */
    public function updatePreferences(CandidateProfile $profile, array $preferences): void
    {
        $profile->fill([
            ...$preferences,
            'work_modes' => $preferences['work_modes'] ?? [],
            'employment_fractions' => $preferences['employment_fractions'] ?? [],
        ]);

        $this->advanceTo($profile, 3);
    }

    /**
     * Step 4: apply the privacy settings and make the profile visible to employers.
     *
     * @param  array<string, mixed>  $privacy
     *
     * @throws ValidationException
     */
    public function publish(CandidateProfile $profile, array $privacy = []): void
    {
        $profile->fill($privacy);

        $this->ensurePublishable($profile);

        $profile->published_at ??= now();
        $this->advanceTo($profile, self::LAST_STEP);
    }

    /**
     * Hide a published profile from employers, or show it again.
     *
     * @throws ValidationException
     */
    public function setVisibility(CandidateProfile $profile, bool $visible): void
    {
        if (! $visible) {
            $profile->update(['published_at' => null]);

            return;
        }

        if ($profile->isPublished()) {
            return;
        }

        $this->ensurePublishable($profile);
        $profile->update(['published_at' => now(), 'onboarding_step' => self::LAST_STEP]);
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
}
