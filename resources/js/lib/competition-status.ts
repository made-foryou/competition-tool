export const COMPETITION_STATUSES = ['draft', 'active', 'finished'] as const;

export type CompetitionStatusValue = (typeof COMPETITION_STATUSES)[number];

type Translate = (
    key: string,
    replacements?: Record<string, string | number>,
) => string;

/**
 * Vertaalt een competitie-statuswaarde naar het label dat in de UI getoond
 * wordt. Onbekende statuswaarden vallen terug op de ruwe waarde in plaats
 * van `undefined` te renderen.
 */
export function competitionStatusLabel(status: string, t: Translate): string {
    const labels: Record<CompetitionStatusValue, string> = {
        draft: t('Draft'),
        active: t('Active'),
        finished: t('Finished'),
    };

    return (labels as Record<string, string>)[status] ?? status;
}
