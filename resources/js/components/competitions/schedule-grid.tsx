import { router, usePage } from '@inertiajs/react';
import { MoreHorizontal, MoveRight, Pin, PinOff, Table2 } from 'lucide-react';
import { Fragment, useId, useRef, useState } from 'react';
import { toast } from 'sonner';
import MatchPinController from '@/actions/App/Http/Controllers/MatchPinController';
import ScheduleMoveDialog from '@/components/competitions/schedule-move-dialog';
import type {
    ScheduleMatchDayProps,
    ScheduleMatchProps,
} from '@/components/competitions/schedule-panel';
import EmptyState from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslations } from '@/hooks/use-translations';
import { formatDate } from '@/lib/format-date';
import { matchStatusBadgeVariant, matchStatusLabel } from '@/lib/match-status';
import { STICKY_COLUMN_CLASSES } from '@/lib/table-classes';
import { cn } from '@/lib/utils';

type Props = {
    competitionId: number;
    matchDay: ScheduleMatchDayProps;
    /** Alle speeldagen, als doelen voor het verplaatsen van een wedstrijd. */
    matchDays: ScheduleMatchDayProps[];
    /**
     * Of het schema bijgestuurd mag worden. Staat dit uit, dan blijft het grid
     * gewoon leesbaar maar verdwijnen de acties per wedstrijd.
     */
    canEdit: boolean;
};

/**
 * Het speelschema van één speeldag: tafels als kolommen, slots als rijen en
 * de pauze als rij over de volle breedte. Wedstrijden die niet op een cel van
 * het huidige raster vallen staan in een aparte lijst onder de tabel in plaats
 * van te verdwijnen.
 */
export default function ScheduleGrid({
    competitionId,
    matchDay,
    matchDays,
    canEdit,
}: Props) {
    const { t } = useTranslations();
    const { locale } = usePage().props;

    // De sr-only caption benoemt zowel de tabel als de scrollbare region
    // eromheen (`aria-labelledby`), zodat die tekst maar één keer bestaat en
    // een schermlezer hem niet twee keer voorleest.
    const captionId = useId();

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

    const fieldNamesById = new Map(
        matchDay.fields.map((field) => [field.id, field.name]),
    );

    /**
     * Eén predicaat voor "past niet in een cel van dit raster": zonder slot,
     * zonder tafel of op een tafel die niet meer bestaat. Zonder deze ene
     * definitie valt een wedstrijd met een geldig slot maar zonder tafel
     * tussen het raster en de lijst eronder door en is hij nergens zichtbaar.
     */
    const isOffGrid = (match: ScheduleMatchProps) =>
        match.slot_index === null ||
        match.field_id === null ||
        !fieldNamesById.has(match.field_id);

    const offGridMatches = matchDay.matches.filter(isOffGrid);

    /**
     * De wedstrijden op slotpositie, zodat elke cel in één opzoekactie weet
     * wat erin staat in plaats van de hele lijst per cel te doorlopen.
     */
    const matchesByCell = new Map<string, ScheduleMatchProps>(
        matchDay.matches
            .filter((match) => !isOffGrid(match))
            .map((match) => [`${match.slot_index}-${match.field_id}`, match]),
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
            {matchDay.matches.length === 0 && (
                <p className="text-muted-foreground text-sm">
                    {t('No matches are scheduled on this match day.')}
                </p>
            )}

            <div className="rounded-xl border">
                <Table aria-labelledby={captionId}>
                    <TableCaption id={captionId} className="sr-only">
                        {t('Schedule for :date from :start to :end', {
                            date: formatDate(matchDay.date, locale),
                            start: matchDay.starts_at,
                            end: matchDay.ends_at,
                        })}
                    </TableCaption>
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
                                                        competitionId={
                                                            competitionId
                                                        }
                                                        match={match}
                                                        matchDays={matchDays}
                                                        currentMatchDayId={
                                                            matchDay.id
                                                        }
                                                        canEdit={canEdit}
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
                                <span className="ms-auto">
                                    <ScheduleMatchActions
                                        competitionId={competitionId}
                                        match={match}
                                        matchDays={matchDays}
                                        currentMatchDayId={matchDay.id}
                                        canEdit={canEdit}
                                    />
                                </span>
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
 *
 * Het streepje achter de eerste speler scheidt de twee namen ook voor een
 * schermlezer, die de twee regels anders als één naam achter elkaar voorleest.
 */
function ScheduleGridCell({
    competitionId,
    match,
    matchDays,
    currentMatchDayId,
    canEdit,
}: MatchActionsProps) {
    return (
        <div className="flex items-start justify-between gap-2">
            <div className="flex flex-col gap-1">
                <span className="text-sm whitespace-normal">
                    {match.first_player}
                    <span className="text-muted-foreground"> –</span>
                </span>
                <span className="text-sm whitespace-normal">
                    {match.second_player}
                </span>
                <MatchMarkers match={match} />
            </div>
            <ScheduleMatchActions
                competitionId={competitionId}
                match={match}
                matchDays={matchDays}
                currentMatchDayId={currentMatchDayId}
                canEdit={canEdit}
            />
        </div>
    );
}

type MatchActionsProps = {
    competitionId: number;
    match: ScheduleMatchProps;
    matchDays: ScheduleMatchDayProps[];
    currentMatchDayId: number;
    canEdit: boolean;
};

/**
 * Het menu met de acties op één wedstrijd: verplaatsen, vastzetten en
 * losmaken. Een gespeelde wedstrijd krijgt geen menu — die is alleen-lezen
 * (besluit 6 van het ontwerp) en de server weigert er sowieso elke wijziging
 * op.
 *
 * Vastzetten en losmaken gaan via `router` in plaats van een `<Form>`: een
 * `DropdownMenuItem` is geen submitknop, en de menu-inhoud hangt in een portal
 * buiten het formulier, dus een formulier eromheen zou de knop niet bereiken.
 * Met `onSelect` werkt het item gewoon met muis én toetsenbord.
 *
 * Het menu sluit bij het openen van de verplaatsdialoog. Dat moet ook: twee
 * modale Radix-lagen over elkaar zetten de dialoog achter het `aria-hidden`
 * van het menu. De dialoog staat daarom buiten het menu, met haar eigen
 * `open`-state, en geeft bij sluiten de focus terug aan de menuknop.
 */
function ScheduleMatchActions({
    competitionId,
    match,
    matchDays,
    currentMatchDayId,
    canEdit,
}: MatchActionsProps) {
    const { t } = useTranslations();
    const [menuOpen, setMenuOpen] = useState(false);
    const [moveOpen, setMoveOpen] = useState(false);
    const triggerRef = useRef<HTMLButtonElement>(null);

    if (!canEdit || match.status === 'played') {
        return null;
    }

    /** Vastzetten betekent "laat staan waar hij staat" en kan dus alleen met een volledige plek. */
    const isScheduled = match.field_id !== null && match.starts_at !== null;

    const onError = () => toast.error(t('Something went wrong.'));

    return (
        <>
            <DropdownMenu open={menuOpen} onOpenChange={setMenuOpen}>
                <DropdownMenuTrigger asChild>
                    <Button
                        ref={triggerRef}
                        variant="ghost"
                        size="icon"
                        className="size-7 shrink-0"
                    >
                        <MoreHorizontal />
                        <span className="sr-only">{t('Match actions')}</span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem
                        onSelect={(event) => {
                            event.preventDefault();
                            setMenuOpen(false);
                            setMoveOpen(true);
                        }}
                    >
                        <MoveRight />
                        {t('Move match')}
                    </DropdownMenuItem>
                    {match.is_pinned ? (
                        <DropdownMenuItem
                            onSelect={() =>
                                router.delete(
                                    MatchPinController.destroy.url([
                                        competitionId,
                                        match.id,
                                    ]),
                                    { preserveScroll: true, onError },
                                )
                            }
                        >
                            <PinOff />
                            {t('Unpin match')}
                        </DropdownMenuItem>
                    ) : (
                        isScheduled && (
                            <DropdownMenuItem
                                onSelect={() =>
                                    router.post(
                                        MatchPinController.store.url([
                                            competitionId,
                                            match.id,
                                        ]),
                                        {},
                                        { preserveScroll: true, onError },
                                    )
                                }
                            >
                                <Pin />
                                {t('Pin match')}
                            </DropdownMenuItem>
                        )
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
            <ScheduleMoveDialog
                competitionId={competitionId}
                match={match}
                matchDays={matchDays}
                currentMatchDayId={currentMatchDayId}
                open={moveOpen}
                onOpenChange={setMoveOpen}
                restoreFocusRef={triggerRef}
            />
        </>
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
                <span className="inline-flex">
                    <Pin className="text-foreground size-4" />
                    <span className="sr-only">{t('Pinned')}</span>
                </span>
            )}
        </span>
    );
}
