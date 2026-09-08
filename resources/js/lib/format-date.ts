/**
 * Parses a `YYYY-MM-DD` date string (as sent by the backend) into a `Date`
 * at local midnight. `new Date(string)` would parse this as UTC midnight,
 * which can shift the displayed day depending on the viewer's timezone.
 */
function parseIsoDate(date: string): Date {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(year, month - 1, day);
}

/**
 * Formats a `YYYY-MM-DD` date string as a long, human-readable date in the
 * given locale, e.g. "1 oktober 2026".
 */
export function formatDate(date: string, locale: string): string {
    return new Intl.DateTimeFormat(locale, {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(parseIsoDate(date));
}
