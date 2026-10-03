<?php

namespace App\Services\JobSharing;

/**
 * Checks a proposed split of the workday between the two members of a job-sharing pair.
 */
class ScheduleValidator
{
    public const int MINIMUM_BLOCK_MINUTES = 60;

    /**
     * Returns human-readable (Polish) problems; an empty list means the schedule is valid.
     *
     * @param  list<int>  $memberIds
     * @param  list<array{candidate_profile_id: int, starts_at: string, ends_at: string}>  $blocks
     * @return list<string>
     */
    public function errors(Workday $workday, array $memberIds, array $blocks): array
    {
        $blockMemberIds = array_map(fn (array $block): int => $block['candidate_profile_id'], $blocks);
        $sortedBlockMemberIds = $blockMemberIds;
        $sortedMemberIds = $memberIds;
        sort($sortedBlockMemberIds);
        sort($sortedMemberIds);

        if ($sortedBlockMemberIds !== $sortedMemberIds) {
            return ['Każda osoba z pary musi mieć dokładnie jeden blok godzin.'];
        }

        $errors = [];
        $intervals = [];

        foreach ($blocks as $block) {
            $startsAt = Workday::toMinutes($block['starts_at']);
            $endsAt = Workday::toMinutes($block['ends_at']);

            if ($endsAt <= $startsAt) {
                $errors[] = 'Koniec bloku musi być później niż początek.';

                continue;
            }

            if ($startsAt < $workday->startsAt || $endsAt > $workday->endsAt) {
                $errors[] = sprintf(
                    'Bloki muszą mieścić się w godzinach pracy %s–%s.',
                    Workday::format($workday->startsAt),
                    Workday::format($workday->endsAt),
                );
            }

            if ($endsAt - $startsAt < self::MINIMUM_BLOCK_MINUTES) {
                $errors[] = 'Każdy blok musi trwać co najmniej godzinę.';
            }

            $intervals[] = [$startsAt, $endsAt];
        }

        if ($errors !== []) {
            return array_values(array_unique($errors));
        }

        usort($intervals, fn (array $first, array $second): int => $first[0] <=> $second[0]);

        $cursor = $workday->startsAt;

        foreach ($intervals as [$startsAt, $endsAt]) {
            if ($startsAt < $cursor) {
                return ['Godziny nie mogą się nakładać.'];
            }

            if ($startsAt > $cursor) {
                return [sprintf('Nikt nie pracuje między %s a %s. Podzielcie cały dzień pracy.', Workday::format($cursor), Workday::format($startsAt))];
            }

            $cursor = $endsAt;
        }

        if ($cursor < $workday->endsAt) {
            return [sprintf('Nikt nie pracuje między %s a %s. Podzielcie cały dzień pracy.', Workday::format($cursor), Workday::format($workday->endsAt))];
        }

        return [];
    }
}
