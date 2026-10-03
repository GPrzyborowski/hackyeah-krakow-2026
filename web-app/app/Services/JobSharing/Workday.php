<?php

namespace App\Services\JobSharing;

use App\Models\JobOffer;

/**
 * Working hours of a job-sharing position, expressed in minutes after midnight.
 */
final readonly class Workday
{
    public const string DEFAULT_STARTS_AT = '08:00';

    public const string DEFAULT_ENDS_AT = '16:00';

    public function __construct(
        public int $startsAt,
        public int $endsAt,
    ) {}

    public static function forOffer(JobOffer $offer): self
    {
        return new self(
            self::toMinutes($offer->workday_starts_at ?? self::DEFAULT_STARTS_AT),
            self::toMinutes($offer->workday_ends_at ?? self::DEFAULT_ENDS_AT),
        );
    }

    /**
     * Converts "HH:MM" or "HH:MM:SS" into minutes after midnight.
     */
    public static function toMinutes(string $time): int
    {
        [$hours, $minutes] = array_map(intval(...), array_pad(explode(':', $time), 2, '0'));

        return $hours * 60 + $minutes;
    }

    public static function format(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    public function lengthInMinutes(): int
    {
        return $this->endsAt - $this->startsAt;
    }

    /**
     * Hours per person when the day is split evenly between two people, e.g. 4.0.
     */
    public function hoursPerPerson(): float
    {
        return round($this->lengthInMinutes() / 60 / 2, 1);
    }

    public function midpoint(): int
    {
        $half = intdiv($this->lengthInMinutes(), 2);

        return $this->startsAt + $half - ($half % 30);
    }

    /**
     * Job-sharing details of an offer for offer cards and detail pages.
     *
     * @return array{is_job_share: bool, workday_starts_at: string|null, workday_ends_at: string|null, hours_per_person: float|null}
     */
    public static function presentOffer(JobOffer $offer): array
    {
        if (! $offer->is_job_share) {
            return ['is_job_share' => false, 'workday_starts_at' => null, 'workday_ends_at' => null, 'hours_per_person' => null];
        }

        $workday = self::forOffer($offer);

        return [
            'is_job_share' => true,
            'workday_starts_at' => self::format($workday->startsAt),
            'workday_ends_at' => self::format($workday->endsAt),
            'hours_per_person' => $workday->hoursPerPerson(),
        ];
    }
}
