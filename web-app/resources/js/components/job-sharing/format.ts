export function toMinutes(time: string): number {
    const [hours = '0', minutes = '0'] = time.split(':');

    return Number(hours) * 60 + Number(minutes);
}

/**
 * "08:00" -> "8:00", like the mockup timeline.
 */
export function formatHour(time: string): string {
    return time.replace(/^0(\d)/, '$1').slice(0, 5);
}

export function formatHours(hours: number): string {
    return `${String(hours).replace('.', ',')} h`;
}

/**
 * Short chip text, e.g. "2 osoby × 4 h".
 */
export function jobShareLabel(hoursPerPerson: number | null): string {
    return hoursPerPerson === null
        ? '2 osoby'
        : `2 osoby × ${formatHours(hoursPerPerson)}`;
}

export function fromMinutes(minutes: number): string {
    const hours = Math.floor(minutes / 60);

    return `${String(hours).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
}

/**
 * Middle of the workday rounded down to a half hour, e.g. 08:00–16:00 -> "12:00".
 */
export function midpoint(startsAt: string, endsAt: string): string {
    const start = toMinutes(startsAt);
    const half = Math.floor((toMinutes(endsAt) - start) / 2);

    return fromMinutes(start + half - (half % 30));
}
