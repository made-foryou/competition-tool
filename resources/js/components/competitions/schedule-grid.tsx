import { Pin, Table2 } from 'lucide-react';
import { Fragment } from 'react';
import type {
    ScheduleMatchDayProps,
    ScheduleMatchProps,
} from '@/components/competitions/schedule-panel';
import EmptyState from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslations } from '@/hooks/use-translations';
import { matchStatusBadgeVariant, matchStatusLabel } from '@/lib/match-status';
import { cn } from '@/lib/utils';

/**
 * Achtergrond op de sticky eerste kolom met de slottijden, zodat de
 * horizontaal scrollende tafelkolommen er niet onderdoor schuiven (ook niet
 * in dark mode). Effen `bg-background` in plaats van een halftransparante
 * kleur, om dezelfde reden als de sticky kolom in `availability-matrix.tsx`.
 */
const STICKY_COLUMN_CLASSES =
    'sticky left-0 z-10 bg-background group-hover:bg-muted';

type Props = {
    matchDay: ScheduleMatchDayProps;
};

/**
 * Het speelschema van één speeldag: tafels als kolommen, slots als rijen en
 * de pauze als rij over de volle breedte. Wedstrijden die niet meer op een
 * slotgrens van het huidige raster beginnen (`slot_index === null`) staan in
 * een aparte lijst onder de tabel in plaats van te verdwijnen.
 */
export default function ScheduleGrid({ matchDay }: Props) {
    const { t } = useTranslations();

    if (matchDay.fields.length === 0) {
        return (
            <EmptyState
                icon={Table2}
                size="sm"
                title={t('This match day has no fields yet.')}
            />
        );
    }

    if (matchDay.slots.length === 0) {
        return (
            <EmptyState
                icon={Table2}
                size="sm"
                title={t(
                    'No slot fits in the opening hours of this match day.',
                )}
                description={t(
                    'Shorten the match duration or extend the opening hours in the planning settings.',
                )}
            />
        );
    }

    /**
     * De wedstrijden op slotpositie, zodat elke cel in één opzoekactie weet
     * wat erin staat in plaats van de hele lijst per cel te doorlopen.
     */
    const matchesByCell = new Map<string, ScheduleMatchProps>(
        matchDay.matches
            .filter((match) => match.slot_index !== null)
            .map((match) => [`${match.slot_index}-${match.field_id}`, match]),
    );

    const fieldNamesById = new Map(
        matchDay.fields.map((field) => [field.id, field.name]),
    );

    const offGridMatches = matchDay.matches.filter(
        (match) => match.slot_index === null,
    );

    const breakStartsAt = matchDay.break?.starts_at ?? null;

    /**
     * De pauzerij komt vóór het eerste slot dat op of na de pauze begint.
     * Kloktijden in `H:i` zijn lexicografisch te vergelijken, dus daar is
     * geen datumparsing voor nodig.
     */
    const breakBeforeSlotIndex =
        breakStartsAt === null
            ? null
            : (matchDay.slots.find((slot) => slot.starts_at >= breakStartsAt)
                  ?.index ?? null);

    return (
        <div className="flex flex-col gap-4">
            <div className="rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead
                                scope="col"
                                className={cn('p-3', STICKY_COLUMN_CLASSES)}
                            >
                                {t('Time')}
                            </TableHead>
                            {matchDay.fields.map((field) => (
                                <TableHead
                                    key={field.id}
                                    scope="col"
                                    className="p-3"
                                >
                                    {field.name}
                                </TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {matchDay.slots.map((slot) => (
                            <Fragment key={slot.index}>
                                {matchDay.break &&
                                    breakBeforeSlotIndex === slot.index && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={
                                                    matchDay.fields.length + 1
                                                }
                                                className="bg-muted/50 text-muted-foreground p-3 text-center text-sm"
                                            >
                                                {t('Break')}{' '}
                                                {matchDay.break.starts_at} –{' '}
                                                {matchDay.break.ends_at}
                                            </TableCell>
                                        </TableRow>
                                    )}
                                <TableRow className="group">
                                    <TableHead
                                        scope="row"
                                        className={cn(
                                            'p-3',
                                            STICKY_COLUMN_CLASSES,
                                        )}
                                    >
                                        {slot.starts_at}
                                        <span className="text-muted-foreground hidden font-normal sm:inline">
                                            {' '}
                                            – {slot.ends_at}
                                        </span>
                                    </TableHead>
                                    {matchDay.fields.map((field) => {
                                        const match = matchesByCell.get(
                                            `${slot.index}-${field.id}`,
                                        );

                                        return (
                                            <TableCell
                                                key={field.id}
                                                className="p-3 align-top"
                                            >
                                                {match && (
                                                    <ScheduleGridCell
                                                        match={match}
                                                    />
                                                )}
                                            </TableCell>
                                        );
                                    })}
                                </TableRow>
                            </Fragment>
                        ))}
                    </TableBody>
                </Table>
            </div>

            {offGridMatches.length > 0 && (
                <div className="flex flex-col gap-2">
                    <h4 className="text-sm font-medium">
                        {t('Outside the current time grid')}
                    </h4>
                    <p className="text-muted-foreground text-sm">
                        {t(
                            'These matches were scheduled with different planning settings or opening hours. Rescheduling puts them back on the grid.',
                        )}
                    </p>
                    <ul className="divide-y rounded-xl border">
                        {offGridMatches.map((match) => (
                            <li
                                key={match.id}
                                className="flex flex-wrap items-center gap-x-2 gap-y-1 p-3 text-sm"
                            >
                                <span className="text-muted-foreground">
                                    {match.starts_at === null
                                        ? t('Not scheduled')
                                        : `${match.starts_at} – ${match.ends_at}`}{' '}
                                    ·{' '}
                                    {(match.field_id === null
                                        ? null
                                        : fieldNamesById.get(match.field_id)) ??
                                        t('No field')}
                                </span>
                                <span>
                                    {match.first_player} – {match.second_player}
                                </span>
                                <MatchMarkers match={match} />
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}

/**
 * De inhoud van één gevulde gridcel: beide spelers onder elkaar en daaronder
 * de markeringen. Alleen gespeelde wedstrijden krijgen een statusbadge; "nog
 * te spelen" is de normale toestand en zou het grid alleen maar voller maken.
 */
function ScheduleGridCell({ match }: { match: ScheduleMatchProps }) {
    return (
        <div className="flex flex-col gap-1">
            <span className="text-sm whitespace-normal">
                {match.first_player}
            </span>
            <span className="text-sm whitespace-normal">
                {match.second_player}
            </span>
            <MatchMarkers match={match} />
        </div>
    );
}

/** De statusbadge (alleen bij gespeeld) en het pin-icoon van een wedstrijd. */
function MatchMarkers({ match }: { match: ScheduleMatchProps }) {
    const { t } = useTranslations();

    if (match.status !== 'played' && !match.is_pinned) {
        return null;
    }

    return (
        <span className="flex items-center gap-1.5">
            {match.status === 'played' && (
                <Badge variant={matchStatusBadgeVariant(match.status)}>
                    {matchStatusLabel(match.status, t)}
                </Badge>
            )}
            {match.is_pinned && (
                <span className="inline-flex" title={t('Pinned')}>
                    <Pin className="text-muted-foreground size-3.5" />
                    <span className="sr-only">{t('Pinned')}</span>
                </span>
            )}
        </span>
    );
}
