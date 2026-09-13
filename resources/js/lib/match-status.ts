export const MATCH_STATUSES = ['pending', 'played'] as const;

export type MatchStatusValue = (typeof MATCH_STATUSES)[number];

type Translate = (
    key: string,
    replacements?: Record<string, string | number>,
) => string;

/**
 * Vertaalt een wedstrijdstatuswaarde naar het label dat in de UI getoond
 * wordt. Onbekende statuswaarden vallen terug op de ruwe waarde in plaats
 * van `undefined` te renderen.
 */
export function matchStatusLabel(status: string, t: Translate): string {
    const labels: Record<MatchStatusValue, string> = {
        pending: t('To play'),
        played: t('Played'),
    };

    return (labels as Record<string, string>)[status] ?? status;
}
