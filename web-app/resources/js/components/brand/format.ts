const ratingFormatter = new Intl.NumberFormat('pl-PL', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
});
const monthFormatter = new Intl.DateTimeFormat('pl-PL', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

/**
 * Groups thousands with a non-breaking space, also for 4-digit numbers ("7 500").
 */
function formatAmount(amount: number): string {
    return String(amount).replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0');
}

/**
 * "8 500–11 000 zł brutto", or null when the offer has no salary range.
 */
export function formatSalary(
    min: number | null,
    max: number | null,
): string | null {
    if (min === null && max === null) {
        return null;
    }

    if (min !== null && max !== null) {
        return `${formatAmount(min)}–${formatAmount(max)} zł brutto`;
    }

    return `${min !== null ? 'od' : 'do'} ${formatAmount((min ?? max) as number)} zł brutto`;
}

/**
 * Polish decimal rating, e.g. "4,6".
 */
export function formatRating(rating: number | null): string {
    return rating === null ? '–' : ratingFormatter.format(rating);
}

/**
 * Short Polish date, e.g. "1 wrz 2027".
 */
export function formatShortDate(isoDate: string): string {
    return monthFormatter.format(new Date(`${isoDate}T00:00:00`));
}
