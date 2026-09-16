export const SCHEDULING_FAILURES = [
    'no_shared_match_day',
    'max_matches_per_day_reached',
    'no_capacity',
] as const;

export type SchedulingFailureValue = (typeof SCHEDULING_FAILURES)[number];

type Translate = (
    key: string,
    replacements?: Record<string, string | number>,
) => string;

/**
 * Vertaalt de reden waarom de planner een wedstrijd niet kon plaatsen naar
 * het korte label in de UI. `null` betekent dat de planner deze wedstrijd
 * nooit heeft geprobeerd; onbekende waarden vallen terug op de ruwe waarde in
 * plaats van `undefined` te renderen.
 */
export function schedulingFailureLabel(
    reason: string | null,
    t: Translate,
): string {
    if (reason === null) {
        return t('Not tried yet');
    }

    const labels: Record<SchedulingFailureValue, string> = {
        no_shared_match_day: t('No shared match day'),
        max_matches_per_day_reached: t('Maximum matches per day reached'),
        no_capacity: t('No free slot'),
    };

    return (labels as Record<string, string>)[reason] ?? reason;
}

/**
 * Eén zin uitleg per reden, voor het planningsrapport. Geeft `null` terug
 * wanneer er niets uit te leggen valt (nooit geprobeerd of een onbekende
 * reden), zodat de UI de regel dan gewoon weglaat.
 */
export function schedulingFailureDescription(
    reason: string | null,
    t: Translate,
): string | null {
    if (reason === null) {
        return null;
    }

    const descriptions: Record<SchedulingFailureValue, string> = {
        no_shared_match_day: t(
            'These two players are never available on the same match day.',
        ),
        max_matches_per_day_reached: t(
            'One of the players already plays the maximum number of matches on every shared match day.',
        ),
        no_capacity: t(
            'Every table slot on the shared match days is already taken.',
        ),
    };

    return (descriptions as Record<string, string>)[reason] ?? null;
}
