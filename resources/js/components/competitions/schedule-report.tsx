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
};

/**
 * Het planningsrapport onder het grid: wat er niet ingepland kon worden en
 * waarom, en welke spelers te weinig rust tussen twee wedstrijden hebben.
 * Rendert niets zolang er niets te melden valt.
 */
export default function ScheduleReport({ unscheduled, restViolations }: Props) {
    const { t } = useTranslations();

    if (unscheduled.length === 0 && restViolations.length === 0) {
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
        <div className="flex flex-col gap-6">
            {unscheduled.length > 0 && (
                <section className="flex flex-col gap-3">
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
                </section>
            )}

            {restViolations.length > 0 && (
                <section className="flex flex-col gap-3">
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
                                {t(
                                    ':player has only :minutes minutes between two matches.',
                                    {
                                        player: violation.player,
                                        minutes: violation.gap_minutes,
                                    },
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </div>
    );
}
