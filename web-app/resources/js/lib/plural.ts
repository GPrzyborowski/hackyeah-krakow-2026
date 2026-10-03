/**
 * Polish plural form picker: one (1) / few (2–4, but not 12–14) / many (0, 5+).
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

/**
 * "1 opinia", "3 opinie", "5 opinii".
 */
export function reviewCountLabel(count: number): string {
    return `${count} ${pluralize(count, 'opinia', 'opinie', 'opinii')}`;
}

/**
 * "1 opinia rodzica", "3 opinie rodziców", "5 opinii rodziców" – one review is written by one parent.
 */
export function parentReviewCountLabel(count: number): string {
    return `${reviewCountLabel(count)} ${count === 1 ? 'rodzica' : 'rodziców'}`;
}
