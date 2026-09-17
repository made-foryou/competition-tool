import { Form } from '@inertiajs/react';
import { CalendarClock, CalendarDays, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import CompetitionScheduleController from '@/actions/App/Http/Controllers/CompetitionScheduleController';
import ScheduleDayPicker from '@/components/competitions/schedule-day-picker';
import ScheduleGrid from '@/components/competitions/schedule-grid';
import ScheduleReport from '@/components/competitions/schedule-report';
import ConfirmDialog from '@/components/confirm-dialog';
import EmptyState from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import type { CompetitionStatusValue } from '@/lib/competition-status';
import { pluralize } from '@/lib/plural';

/** Koppelt de uitleg onder de inplanknop aan die knop via `aria-describedby`. */
const SCHEDULE_DISABLED_REASON_ID = 'schedule-disabled-hint';

/** De omvang van het skeletraster: een speeldag met een paar tafels. */
const SKELETON_ROWS = 6;
const SKELETON_COLUMNS = 3;

export type ScheduleFieldProps = {
    id: number;
    name: string;
    position: number;
};

export type ScheduleSlotProps = {
    index: number;
    starts_at: string;
    ends_at: string;
};

export type ScheduleMatchProps = {
    id: number;
    field_id: number | null;
    starts_at: string | null;
    ends_at: string | null;
    first_player: string;
    second_player: string;
    status: string;
    is_pinned: boolean;
    /** `null` zodra de wedstrijd niet meer op een slotgrens van het huidige raster begint. */
    slot_index: number | null;
};

export type ScheduleMatchDayProps = {
    id: number;
    date: string;
    starts_at: string;
    ends_at: string;
    fields: ScheduleFieldProps[];
    slots: ScheduleSlotProps[];
    break: { starts_at: string; ends_at: string } | null;
    matches: ScheduleMatchProps[];
};

export type ScheduleUnscheduledProps = {
    id: number;
    first_player: string;
    second_player: string;
    /** `null` zolang de planner deze wedstrijd nooit heeft geprobeerd. */
    reason: string | null;
};

export type ScheduleRestViolationProps = {
    match_id: number;
    player: string;
    /** Geklemd op 0; bij `overlapping` zegt dit getal dus niets. */
    gap_minutes: number;
    /** De twee wedstrijden van deze speler overlappen elkaar. */
    overlapping: boolean;
};

export type ScheduleBlockedReason =
    | 'inactive'
    | 'no_match_days'
    | 'no_fields'
    | 'no_availability'
    | 'no_matches'
    | 'nothing_to_schedule'
    | null;

/** Spiegelt `SummarizesSchedule::scheduleProps()` één op één. */
export type ScheduleProps = {
    can_schedule: boolean;
    blocked_reason: ScheduleBlockedReason;
    /**
     * De status van de competitie. Nodig naast `blocked_reason`: die zegt
     * alleen dát er niet gepland mag worden, terwijl een afgeronde competitie
     * een andere uitleg verdient dan een concept.
     */
    status: CompetitionStatusValue;
    summary: {
        total: number;
        scheduled: number;
        unscheduled: number;
        played: number;
        pinned: number;
    };
    match_days: ScheduleMatchDayProps[];
    unscheduled: ScheduleUnscheduledProps[];
    rest_violations: ScheduleRestViolationProps[];
};

type Props = {
    competitionId: number;
    schedule: ScheduleProps;
    /** Springt naar de tab Speeldagen. Toont een CTA wanneer meegegeven. */
    onNavigateToMatchDays?: () => void;
    /** Springt naar de tab Beschikbaarheid. Toont een CTA wanneer meegegeven. */
    onNavigateToAvailability?: () => void;
    /** Springt naar de tab Deelnemers. Toont een CTA wanneer meegegeven. */
    onNavigateToParticipants?: () => void;
};

/**
 * De tab "Schema": de inplanknop met de server-bepaalde reden waarom hij niet
 * mag, de tellers, het grid van de gekozen speeldag en het planningsrapport.
 *
 * Het grid wordt ook getoond wanneer er niet (meer) ingepland mag worden —
 * een afgeronde competitie moet haar schema kunnen laten zien. Alleen zonder
 * speeldagen valt er niets te tonen.
 */
export default function SchedulePanel({
    competitionId,
    schedule,
    onNavigateToMatchDays,
    onNavigateToAvailability,
    onNavigateToParticipants,
}: Props) {
    const { t } = useTranslations();

    const [selectedMatchDayId, setSelectedMatchDayId] = useState<number | null>(
        () => schedule.match_days[0]?.id ?? null,
    );

    // Valt terug op de eerste speeldag zodra de gekozen dag uit de props
    // verdwijnt (verwijderd in een andere tab), zonder een effect dat na de
    // eerste render nog een keer zou renderen.
    const selectedMatchDay =
        schedule.match_days.find(
            (matchDay) => matchDay.id === selectedMatchDayId,
        ) ??
        schedule.match_days[0] ??
        null;

    /**
     * De uitleg onder de knoppen — en daarmee de enige regel direct boven het
     * grid die vertelt waarom er niets te bedienen valt.
     *
     * `inactive` splitst op status: bij een afgeronde competitie is "pas de
     * status aan onder Algemeen" verkeerd advies (die competitie is historie),
     * dus die krijgt de alleen-lezen zin. Een concept houdt de bestaande hint,
     * want daar is het activeren van de competitie wél de volgende stap.
     */
    const disabledReason = (() => {
        switch (schedule.blocked_reason) {
            case 'inactive':
                return schedule.status === 'finished'
                    ? t(
                          'This competition is finished. The schedule is read-only.',
                      )
                    : t(
                          'Matches can only be scheduled for an active competition. Change the status under General.',
                      );
            case 'no_match_days':
                return t('Add match days before scheduling.');
            case 'no_fields':
                return t(
                    'Add fields to at least one match day before scheduling.',
                );
            case 'no_availability':
                return t('Nobody has filled in their availability yet.');
            case 'no_matches':
                return t('There are no matches to schedule.');
            case 'nothing_to_schedule':
                return t('All matches are already scheduled.');
            default:
                return null;
        }
    })();

    /**
     * Bijsturen (verplaatsen, vastzetten, opnieuw plannen) hangt aan dezelfde
     * statusguard als de schrijfroutes: `allowsScheduling()`. Van alle redenen
     * die het plannen blokkeren is `inactive` de enige die uit die guard komt;
     * de rest zegt alleen dat er niets te plannen valt. Een afgerond of
     * concept-schema blijft dus leesbaar, maar zonder acties die de server
     * toch met een 403 zou weigeren.
     */
    const canEdit = schedule.blocked_reason !== 'inactive';

    /**
     * Opnieuw plannen kan zodra er iets ingepland staat. Bewust niet aan
     * `can_schedule` gekoppeld: die vlag geldt voor het aanvullen en staat
     * juist op `false` met reden `nothing_to_schedule` zodra alles gepland is
     * — precies de situatie waarin opnieuw plannen zinvol is. De overige
     * redenen blokkeren ook het opnieuw plannen, dus dan verdwijnt de knop.
     */
    const canRebuild =
        schedule.summary.scheduled > 0 &&
        (schedule.can_schedule ||
            schedule.blocked_reason === 'nothing_to_schedule');

    /**
     * De tab waar de beheerder de blokkade kan opheffen. Alleen bij een reden
     * die met één stap te verhelpen is; `inactive` en `nothing_to_schedule`
     * horen thuis in respectievelijk de tab Algemeen en nergens.
     *
     * `no_match_days` staat er bewust niet bij: zonder speeldagen toont de
     * `EmptyState` eronder al een knop "Naar speeldagen", en twee identieke
     * knoppen boven elkaar helpen niemand.
     */
    const resolveAction = (() => {
        switch (schedule.blocked_reason) {
            case 'no_fields':
                return onNavigateToMatchDays
                    ? {
                          label: t('Go to match days'),
                          onClick: onNavigateToMatchDays,
                      }
                    : null;
            case 'no_availability':
                return onNavigateToAvailability
                    ? {
                          label: t('Go to availability'),
                          onClick: onNavigateToAvailability,
                      }
                    : null;
            case 'no_matches':
                return onNavigateToParticipants
                    ? {
                          label: t('Go to participants'),
                          onClick: onNavigateToParticipants,
                      }
                    : null;
            default:
                return null;
        }
    })();

    return (
        <section className="flex flex-col gap-4">
            <div className="flex flex-col gap-1">
                <h3 className="text-sm font-medium">{t('Schedule')}</h3>
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-muted-foreground text-sm">
                        {t(
                            'Scheduling fills empty slots around the existing schedule. Played and pinned matches never move.',
                        )}{' '}
                        {t(
                            'Rescheduling clears everything that is not played or pinned and plans it again.',
                        )}
                    </p>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline">
                            {t(':scheduled of :total scheduled', {
                                scheduled: schedule.summary.scheduled,
                                total: schedule.summary.total,
                            })}
                        </Badge>
                        {/* Zolang er nog niets is ingepland zegt ":scheduled
                        van :total ingepland" hetzelfde; de tweede badge voegt
                        dan alleen ruis toe. */}
                        {schedule.summary.unscheduled > 0 &&
                            schedule.summary.scheduled > 0 && (
                                <Badge variant="secondary">
                                    {pluralize(
                                        t,
                                        schedule.summary.unscheduled,
                                        ':count match not scheduled',
                                        ':count matches not scheduled',
                                    )}
                                </Badge>
                            )}
                        {schedule.summary.played > 0 && (
                            <Badge variant="outline">
                                {t(':count played', {
                                    count: schedule.summary.played,
                                })}
                            </Badge>
                        )}
                        {schedule.summary.pinned > 0 && (
                            <Badge variant="outline">
                                {t(':count pinned', {
                                    count: schedule.summary.pinned,
                                })}
                            </Badge>
                        )}
                    </div>
                </div>
            </div>

            <div className="flex flex-col gap-2">
                <div className="flex flex-wrap gap-2">
                    {schedule.can_schedule ? (
                        <Form
                            {...CompetitionScheduleController.store.form(
                                competitionId,
                            )}
                            options={{ preserveScroll: true }}
                            onError={() =>
                                toast.error(t('Something went wrong.'))
                            }
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    <CalendarClock />
                                    {t('Schedule matches')}
                                </Button>
                            )}
                        </Form>
                    ) : (
                        <Button
                            size="sm"
                            aria-disabled="true"
                            aria-describedby={
                                disabledReason
                                    ? SCHEDULE_DISABLED_REASON_ID
                                    : undefined
                            }
                            className="aria-disabled:pointer-events-auto aria-disabled:opacity-50"
                            onClick={(event) => event.preventDefault()}
                        >
                            <CalendarClock />
                            {t('Schedule matches')}
                        </Button>
                    )}
                    {canRebuild && (
                        <ConfirmDialog
                            trigger={
                                // Zodra de inplanknop dood is, is opnieuw
                                // plannen de enige knop die nog iets doet;
                                // dan hoort de visuele nadruk daarop te
                                // liggen in plaats van op de dode knop.
                                <Button
                                    variant={
                                        schedule.can_schedule
                                            ? 'outline'
                                            : 'default'
                                    }
                                    size="sm"
                                >
                                    <RefreshCw />
                                    {t('Reschedule everything')}
                                </Button>
                            }
                            title={t('Reschedule everything?')}
                            description={t(
                                'This clears the current schedule except played and pinned matches, and plans everything again. Participants may see their matches move.',
                            )}
                            action={CompetitionScheduleController.rebuild.form(
                                competitionId,
                            )}
                            confirmLabel={t('Reschedule everything')}
                            confirmVariant="destructive"
                        />
                    )}
                    {resolveAction && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={resolveAction.onClick}
                        >
                            {resolveAction.label}
                        </Button>
                    )}
                </div>
                {disabledReason && (
                    <p
                        id={SCHEDULE_DISABLED_REASON_ID}
                        className="text-muted-foreground text-sm"
                    >
                        {disabledReason}
                    </p>
                )}
            </div>

            {selectedMatchDay === null ? (
                <EmptyState
                    icon={CalendarDays}
                    title={t('No schedule yet.')}
                    description={t(
                        'The schedule appears as soon as the competition has match days with fields.',
                    )}
                    action={
                        onNavigateToMatchDays && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={onNavigateToMatchDays}
                            >
                                {t('Go to match days')}
                            </Button>
                        )
                    }
                />
            ) : (
                <>
                    <ScheduleDayPicker
                        matchDays={schedule.match_days}
                        selectedMatchDayId={selectedMatchDay.id}
                        onSelect={setSelectedMatchDayId}
                    />
                    <ScheduleGrid
                        competitionId={competitionId}
                        matchDay={selectedMatchDay}
                        matchDays={schedule.match_days}
                        canEdit={canEdit}
                    />
                    <ScheduleReport
                        competitionId={competitionId}
                        unscheduled={schedule.unscheduled}
                        restViolations={schedule.rest_violations}
                        scheduledCount={schedule.summary.scheduled}
                        matchDays={schedule.match_days}
                        canEdit={canEdit}
                    />
                </>
            )}
        </section>
    );
}

/**
 * Placeholder in dezelfde omtrek als de geladen tab: kop, samenvattingsbadges,
 * actiebalk en het grid. Ook de rijen bóven het grid zitten erin, want anders
 * schuift de actiebalk alsnog omlaag zodra de uitgestelde schedule-prop
 * binnenkomt.
 */
export function ScheduleSkeleton() {
    const { t } = useTranslations();

    return (
        <div
            className="flex flex-col gap-4"
            aria-label={t('Loading schedule…')}
            aria-busy="true"
        >
            <div className="flex flex-col gap-2">
                <Skeleton className="h-4 w-24" />
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Skeleton className="h-4 w-72 max-w-full" />
                    <div className="flex gap-2">
                        <Skeleton className="h-5 w-28 rounded-md" />
                        <Skeleton className="h-5 w-24 rounded-md" />
                    </div>
                </div>
            </div>

            <div className="flex gap-2">
                <Skeleton className="h-8 w-44 rounded-md" />
            </div>

            <div className="flex flex-col gap-3 rounded-xl border p-3">
                <div className="flex items-center gap-3">
                    <Skeleton className="h-4 w-16" />
                    {Array.from({ length: SKELETON_COLUMNS }, (_, column) => (
                        <Skeleton key={column} className="h-4 flex-1" />
                    ))}
                </div>
                {Array.from({ length: SKELETON_ROWS }, (_, row) => (
                    <div key={row} className="flex items-center gap-3">
                        <Skeleton className="h-8 w-16" />
                        {Array.from(
                            { length: SKELETON_COLUMNS },
                            (_, column) => (
                                <Skeleton key={column} className="h-8 flex-1" />
                            ),
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}
