const shortMonths = [
    'sty',
    'lut',
    'mar',
    'kwi',
    'maj',
    'cze',
    'lip',
    'sie',
    'wrz',
    'paź',
    'lis',
    'gru',
];

function parseDate(value: string): Date {
    const [year, month, day] = value.slice(0, 10).split('-').map(Number);

    return new Date(year, month - 1, day);
}

/**
 * "1 wrz" or "1 wrz 2027" style date used across the mockups.
 */
export function formatShortDate(
    value: string | null | undefined,
    withYear = false,
): string {
    if (!value) {
        return '';
    }

    const date = parseDate(value);
    const base = `${date.getDate()} ${shortMonths[date.getMonth()]}`;

    return withYear ? `${base} ${date.getFullYear()}` : base;
}

export function formatLongDate(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleDateString('pl-PL', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

/**
 * "8 500–11 000 zł brutto".
 */
export function formatSalary(
    min: number | null,
    max: number | null,
): string | null {
    const format = (amount: number) =>
        new Intl.NumberFormat('pl-PL', { useGrouping: true })
            .format(amount)
            .replace(/\s/g, ' ');

    if (min !== null && max !== null) {
        return `${format(min)}–${format(max)} zł brutto`;
    }

    if (min !== null) {
        return `od ${format(min)} zł brutto`;
    }

    if (max !== null) {
        return `do ${format(max)} zł brutto`;
    }

    return null;
}

export function formatRating(rating: number | null): string {
    return rating === null
        ? ''
        : rating.toLocaleString('pl-PL', {
              minimumFractionDigits: 1,
              maximumFractionDigits: 1,
          });
}

export function formatFileSize(bytes: number | null): string {
    if (bytes === null) {
        return '';
    }

    return bytes > 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/**
 * Polish plural form picker: one / few (2-4) / many.
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

    return lastDigit >= 2 &&
        lastDigit <= 4 &&
        (lastTwoDigits < 12 || lastTwoDigits > 14)
        ? few
        : many;
}
