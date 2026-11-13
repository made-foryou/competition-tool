export const POINTS_SYSTEMS = [
    'win-2-loss-0',
    'win-2-loss-1',
    'games-won',
] as const;

export type PointsSystemValue = (typeof POINTS_SYSTEMS)[number];

type Translate = (
    key: string,
    replacements?: Record<string, string | number>,
) => string;

/**
 * Vertaalt een puntentelling naar het korte label in de UI. Onbekende
 * waarden vallen terug op de ruwe waarde in plaats van `undefined` te
 * renderen.
 */
export function pointsSystemLabel(value: string, t: Translate): string {
    const labels: Record<PointsSystemValue, string> = {
        'win-2-loss-0': t('Win 2, loss 0'),
        'win-2-loss-1': t('Win 2, loss 1 (ITTF)'),
        'games-won': t('1 point per game won'),
    };

    return (labels as Record<string, string>)[value] ?? value;
}

/**
 * Eén zin uitleg per puntentelling. Geeft `null` terug bij een onbekende
 * waarde, zodat de UI de regel dan gewoon weglaat.
 */
export function pointsSystemDescription(
    value: string,
    t: Translate,
): string | null {
    const descriptions: Record<PointsSystemValue, string> = {
        'win-2-loss-0': t('A win earns 2 points, a loss none.'),
        'win-2-loss-1': t(
            'A win earns 2 points and a loss 1 point, as in the ITTF rules for groups. Playing more matches also earns more points.',
        ),
        'games-won': t('Every game won earns 1 point, also in a lost match.'),
    };

    return (descriptions as Record<string, string>)[value] ?? null;
}
