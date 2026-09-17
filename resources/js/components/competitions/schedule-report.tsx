import type {
    ScheduleRestViolationProps,
    ScheduleUnscheduledProps,
} from '@/components/competitions/schedule-panel';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/hooks/use-translations';
import {
    schedulingFailureDescription,
    schedulingFailureLabel,
} from '@/lib/scheduling-failure';

/**
 * De volgorde waarin de redenen onder "Niet ingepland" verschijnen: van de
 * oorzaak die de beheerder alleen met beschikbaarheid kan oplossen naar de
 * oorzaak die met meer capaciteit op te lossen is. `null` (nooit geprobeerd)
 * sluit de rij.
 */
const REASON_ORDER = [
    'no_shared_match_day',
    'max_matches_per_day_reached',
    'no_capacity',
    null,
] as const;

type Props = {
    unscheduled: ScheduleUnscheduledProps[];
    restViolations: ScheduleRestViolationProps[];
    /** Het aantal ingeplande wedstrijden van de hele competitie. */
    scheduledCount: number;
};

/**
 * Het planningsrapport onder het grid: wat er niet ingepland kon worden en
 * waarom, en welke spelers te weinig rust tussen twee wedstrijden hebben.
 *
 * Het rapport gaat over de hele competitie en niet over de gekozen speeldag;
 * daarom staat het als eigen sectie onder het grid, met een kop en die
 * toelichting erbij. Zolang er nog nooit iets is ingepland én er niets te
 * melden valt, blijft het weg.
 */
export default function ScheduleReport({
    unscheduled,
    restViolations,
    scheduledCount,
}: Props) {
    const { t } = useTranslations();

    const hasFindings = unscheduled.length > 0 || restViolations.length > 0;

    if (!hasFindings && scheduledCount === 0) {
        return null;
    }

    /**
     * Onbekende redenen (een nieuwere server dan deze build) horen ook in het
     * rapport thuis, dus na de bekende volgorde volgen de overgebleven
     * redenen op alfabet.
     */
    const reasons = [
        ...REASON_ORDER,
        ...[
            ...new Set(
                unscheduled
                    .map((match) => match.reason)
                    .filter(
                        (reason): reason is string =>
                            reason !== null &&
                            !REASON_ORDER.some((known) => known === reason),
                    ),
            ),
        ].sort(),
    ];

    return (
        <section className="flex flex-col gap-6 border-t pt-6">
            <div className="flex flex-col gap-1">
                <h3 className="text-sm font-medium">{t('Planning report')}</h3>
                <p className="text-muted-foreground text-sm">
                    {t(
                        'This report covers the whole competition, not just the selected match day.',
                    )}
                </p>
            </div>

            {!hasFindings && (
                <p className="text-muted-foreground text-sm">
                    {t(
                        'Everything fits: no unscheduled matches and no rest-time problems.',
                    )}
                </p>
            )}

            {unscheduled.length > 0 && (
                <div className="flex flex-col gap-3">
                    <div className="flex flex-wrap items-center gap-2">
                        <h4 className="text-sm font-medium">
                            {t('Not scheduled')}
                        </h4>
                        <Badge variant="secondary">{unscheduled.length}</Badge>
                    </div>

                    {reasons.map((reason) => {
                        const matches = unscheduled.filter(
                            (match) => match.reason === reason,
                        );

                        if (matches.length === 0) {
                            return null;
                        }

                        const description = schedulingFailureDescription(
                            reason,
                            t,
                        );

                        return (
                            <div
                                key={reason ?? 'not_tried'}
                                className="flex flex-col gap-2"
                            >
                                <p className="text-sm font-medium">
                                    {schedulingFailureLabel(reason, t)}
                                </p>
                                {description && (
                                    <p className="text-muted-foreground text-sm">
                                        {description}
                                    </p>
                                )}
                                <ul className="divide-y rounded-xl border">
                                    {matches.map((match) => (
                                        <li
                                            key={match.id}
                                            className="p-3 text-sm"
                                        >
                                            {match.first_player} –{' '}
                                            {match.second_player}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        );
                    })}
                </div>
            )}

            {restViolations.length > 0 && (
                <div className="flex flex-col gap-3">
                    <div className="flex flex-wrap items-center gap-2">
                        <h4 className="text-sm font-medium">
                            {t('Rest time not met')}
                        </h4>
                        <Badge variant="secondary">
                            {restViolations.length}
                        </Badge>
                    </div>
                    <p className="text-muted-foreground text-sm">
                        {t(
                            'These players have less rest between two matches than the minimum in the planning settings.',
                        )}
                    </p>
                    <ul className="divide-y rounded-xl border">
                        {restViolations.map((violation, index) => (
                            <li
                                key={`${violation.match_id}-${violation.player}-${index}`}
                                className="p-3 text-sm"
                            >
                                {violation.overlapping
                                    ? t(
                                          ':player has two matches that overlap.',
                                          { player: violation.player },
                                      )
                                    : t(
                                          ':player has only :minutes minutes between two matches.',
                                          {
                                              player: violation.player,
                                              minutes: violation.gap_minutes,
                                          },
                                      )}
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </section>
    );
}
