const shortDate = new Intl.DateTimeFormat('pl-PL', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

const longDate = new Intl.DateTimeFormat('pl-PL', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

/**
 * Groups thousands with a non-breaking space; Intl skips grouping for 4-digit
 * numbers in Polish, which made "8500–11 000" inconsistent.
 */
const money = {
    format: (amount: number): string =>
        String(amount).replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0'),
};

function parseDate(value: string): Date {
    return new Date(`${value.slice(0, 10)}T00:00:00`);
}

export function formatShortDate(value: string | null): string {
    return value ? shortDate.format(parseDate(value)).replace('.', '') : '—';
}

export function formatLongDate(value: string | null): string {
    return value ? longDate.format(parseDate(value)) : '—';
}

export function formatSalaryRange(
    min: number | null,
    max: number | null,
): string | null {
    if (min === null && max === null) {
        return null;
    }

    if (min !== null && max !== null) {
        return `${money.format(min)}–${money.format(max)} zł brutto`;
    }

    return min !== null
        ? `od ${money.format(min)} zł brutto`
        : `do ${money.format(max as number)} zł brutto`;
}

/**
 * Polish plural form, e.g. pluralize(3, 'osoba', 'osoby', 'osób').
 */
export function pluralize(
    count: number,
    one: string,
    few: string,
    many: string,
): string {
    if (count === 1) {
        return one;
    }

    const lastDigit = count % 10;
    const lastTwoDigits = count % 100;

    if (
        lastDigit >= 2 &&
        lastDigit <= 4 &&
        (lastTwoDigits < 12 || lastTwoDigits > 14)
    ) {
        return few;
    }

    return many;
}

export function pluralizeCandidates(count: number): string {
    return pluralize(count, 'kandydatka', 'kandydatki', 'kandydatek');
}
