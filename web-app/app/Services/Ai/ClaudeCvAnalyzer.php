<?php

namespace App\Services\Ai;

use App\Models\CandidateProfile;
use App\Models\Skill;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reads the CV (PDF and/or pasted text) with Claude; falls back to keyword matching on any failure.
 */
class ClaudeCvAnalyzer implements CvAnalyzer
{
    private const int MAX_SKILLS = 12;

    private const int MAX_POSITIONS = 4;

    private const int MAX_EXTRACTED_TEXT_LENGTH = 20000;

    private const string SYSTEM_PROMPT = <<<'PROMPT'
        You analyse CVs for mumjobs, a Polish job platform for pregnant women and mothers returning to work.
        Employers see an anonymous profile, so the summary and headline must never contain personal data
        (names, surnames, e-mail, phone, address, exact dates of birth, photos) and must never mention pregnancy,
        children, maternity or parental leave, or the reason for any career gap.
        Write the summary, headline and position titles in Polish. The summary has exactly two sentences in the
        third person and focuses on experience, strengths and the kind of work the person does well.
        For skills, prefer the exact names from the provided skill dictionary; add a new skill name only when the CV
        clearly shows an important skill missing from the dictionary. Return at most 12 skills, most relevant first.
        Suggest at most 4 positions the person fits, each with a fit score from 0 to 100.
        years_of_experience is the total number of years of professional experience (integer), or null if unknown.
        extracted_text is the plain text of the CV with personal contact data removed.
        PROMPT;

    public function __construct(
        private readonly ClaudeClient $claude,
        private readonly KeywordCvAnalyzer $fallback,
    ) {}

    public function analyze(CandidateProfile $profile): CvAnalysis
    {
        $content = $this->contentBlocks($profile);

        if ($content === null) {
            return $this->fallback->analyze($profile);
        }

        try {
            return $this->toAnalysis($this->claude->json(self::SYSTEM_PROMPT, $content, $this->schema(), 8000, 'medium'));
        } catch (Throwable $exception) {
            Log::warning('Claude CV analysis failed, using keyword fallback.', [
                'candidate_profile_id' => $profile->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->fallback->analyze($profile);
        }
    }

    /**
     * Build the user content: the PDF document block (when present) followed by the instructions and pasted text.
     *
     * @return list<array<string, mixed>>|null
     */
    private function contentBlocks(CandidateProfile $profile): ?array
    {
        $blocks = [];
        $disk = Storage::disk('local');

        if ($profile->cv_path && Str::endsWith(Str::lower($profile->cv_path), '.pdf') && $disk->exists($profile->cv_path)) {
            $blocks[] = [
                'type' => 'document',
                'source' => [
                    'type' => 'base64',
                    'media_type' => 'application/pdf',
                    'data' => base64_encode((string) $disk->get($profile->cv_path)),
                ],
            ];
        }

        $cvText = trim((string) $profile->cv_text);

        if ($blocks === [] && $cvText === '') {
            return null;
        }

        $dictionary = Skill::query()->orderBy('name')->pluck('name')->implode(', ');

        $text = "Skill dictionary: {$dictionary}\n\n";
        $text .= $blocks === [] ? 'Analyse the CV text below.' : 'Analyse the attached CV.';

        if ($cvText !== '') {
            $text .= "\n\nCV text pasted by the candidate:\n<cv_text>\n{$cvText}\n</cv_text>";
        }

        $blocks[] = ['type' => 'text', 'text' => $text];

        return $blocks;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function toAnalysis(array $data): CvAnalysis
    {
        $skills = array_values(collect(is_array($data['skills'] ?? null) ? $data['skills'] : [])
            ->filter(fn (mixed $skill): bool => is_string($skill) && trim($skill) !== '')
            ->map(fn (string $skill): string => Str::limit(trim($skill), 60, ''))
            ->unique(fn (string $skill): string => Str::lower($skill))
            ->take(self::MAX_SKILLS)
            ->all());

        $positions = array_values(collect(is_array($data['positions'] ?? null) ? $data['positions'] : [])
            ->filter(fn (mixed $position): bool => is_array($position) && is_string($position['title'] ?? null))
            ->map(fn (array $position): array => [
                'title' => Str::limit(trim($position['title']), 80, ''),
                'score' => max(0, min(100, (int) ($position['score'] ?? 0))),
            ])
            ->sortByDesc('score')
            ->take(self::MAX_POSITIONS)
            ->all());

        $years = $data['years_of_experience'] ?? null;

        return new CvAnalysis(
            skills: $skills,
            positions: $positions,
            summary: $this->nullableString($data['summary'] ?? null),
            headline: $this->nullableString($data['headline'] ?? null, 120),
            yearsOfExperience: is_numeric($years) ? max(0, min(60, (int) $years)) : null,
            extractedText: $this->nullableString($data['extracted_text'] ?? null, self::MAX_EXTRACTED_TEXT_LENGTH),
        );
    }

    private function nullableString(mixed $value, int $limit = 1000): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Str::limit(trim($value), $limit, '');
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['skills', 'positions', 'summary', 'headline', 'years_of_experience', 'extracted_text'],
            'properties' => [
                'skills' => ['type' => 'array', 'items' => ['type' => 'string']],
                'positions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['title', 'score'],
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'score' => ['type' => 'integer'],
                        ],
                    ],
                ],
                'summary' => ['type' => 'string'],
                'headline' => ['type' => 'string'],
                'years_of_experience' => ['anyOf' => [['type' => 'integer'], ['type' => 'null']]],
                'extracted_text' => ['type' => 'string'],
            ],
        ];
    }
}
