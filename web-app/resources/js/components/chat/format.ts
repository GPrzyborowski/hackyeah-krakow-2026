const timeFormatter = new Intl.DateTimeFormat('pl-PL', {
    hour: '2-digit',
    minute: '2-digit',
});

const dayFormatter = new Intl.DateTimeFormat('pl-PL', {
    day: 'numeric',
    month: 'short',
});

/**
 * "14:05" for today, "3 paź" for older timestamps.
 */
export function formatMessageTime(value: string | null): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const isToday = date.toDateString() === new Date().toDateString();

    return isToday ? timeFormatter.format(date) : dayFormatter.format(date);
}

/**
 * "14:05" or "3 paź, 14:05" shown under a chat bubble.
 */
export function formatBubbleTime(value: string): string {
    const date = new Date(value);
    const isToday = date.toDateString() === new Date().toDateString();

    return isToday
        ? timeFormatter.format(date)
        : `${dayFormatter.format(date)}, ${timeFormatter.format(date)}`;
}
