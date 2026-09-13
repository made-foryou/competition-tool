export const COMPETITION_TYPES = ['theo-schilthuizen-bokaal'] as const;

export type CompetitionTypeValue = (typeof COMPETITION_TYPES)[number];

type Translate = (
    key: string,
    replacements?: Record<string, string | number>,
) => string;

/**
 * Vertaalt een competitietypewaarde naar het label dat in de UI getoond
 * wordt. Onbekende typewaarden vallen terug op de ruwe waarde in plaats van
 * `undefined` te renderen.
 */
export function competitionTypeLabel(type: string, t: Translate): string {
    const labels: Record<CompetitionTypeValue, string> = {
        'theo-schilthuizen-bokaal': t('Theo Schilthuizen trophy'),
    };

    return (labels as Record<string, string>)[type] ?? type;
}
