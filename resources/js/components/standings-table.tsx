import { Trophy } from 'lucide-react';
import type { ReactNode } from 'react';
import EmptyState from '@/components/empty-state';
import { Skeleton } from '@/components/ui/skeleton';
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
import { pointsSystemLabel } from '@/lib/points-system';
import type { PointsSystemValue } from '@/lib/points-system';
import { cn } from '@/lib/utils';

export type StandingRow = {
    player_id: number;
    position: number;
    shares_position: boolean;
    player: string;
    player_is_participant: boolean;
    played: number;
    won: number;
    lost: number;
    game_difference: number;
    points: number;
    is_viewer: boolean;
};

export type StandingsProps = {
    points_system: PointsSystemValue;
    rows: StandingRow[];
};

export type StandingsSummary = {
    points_system: PointsSystemValue;
    leaders: StandingRow[];
    viewer_row: StandingRow | null;
    has_gap: boolean;
    total: number;
};

const COLUMN_COUNT = 7;

const NUMBER_CELL_CLASSES = 'px-1 text-right tabular-nums sm:px-2';

/**
 * Het game-saldo met teken: `+3`, `0` of `−2` (echt minteken, U+2212).
 */
function formatGameDifference(value: number): string {
    if (value > 0) {
        return `+${value}`;
    }

    if (value < 0) {
        return `−${Math.abs(value)}`;
    }

    return '0';
}

/**
 * Kolomkop met een korte, visuele variant onder `sm` en de volledige tekst
 * voor schermlezers en vanaf `sm`.
 */
function ColumnHead({
    short,
    full,
    className,
}: {
    short: string;
    full: string;
    className?: string;
}) {
    return (
        <TableHead scope="col" className={className}>
            <span aria-hidden="true" className="sm:hidden">
                {short}
            </span>
            <span className="sr-only sm:not-sr-only">{full}</span>
        </TableHead>
    );
}

function StandingTableRow({ row }: { row: StandingRow }) {
    const { t } = useTranslations();
    const hasBadges = row.is_viewer || !row.player_is_participant;

    return (
        <TableRow className={cn(row.is_viewer && 'bg-muted/50 font-medium')}>
            <TableCell className={NUMBER_CELL_CLASSES}>
                {row.position}
                {row.shares_position && (
                    <span className="sr-only">, {t('shared place')}</span>
                )}
            </TableCell>
            <TableCell className="[overflow-wrap:anywhere] whitespace-normal">
                <div className="flex flex-col gap-1">
                    <span>{row.player}</span>
                    {hasBadges && (
                        <div className="flex flex-wrap gap-1">
                            {row.is_viewer && (
                                <Badge
                                    variant="secondary"
                                    className="whitespace-normal"
                                >
                                    {t('You')}
                                </Badge>
                            )}
                            {!row.player_is_participant && (
                                <Badge
                                    variant="outline"
                                    className="whitespace-normal"
                                >
                                    {t('No longer participating')}
                                </Badge>
                            )}
                        </div>
                    )}
                </div>
            </TableCell>
            <TableCell className={NUMBER_CELL_CLASSES}>{row.played}</TableCell>
            <TableCell className={NUMBER_CELL_CLASSES}>{row.won}</TableCell>
            <TableCell className={NUMBER_CELL_CLASSES}>{row.lost}</TableCell>
            <TableCell className={NUMBER_CELL_CLASSES}>
                {formatGameDifference(row.game_difference)}
            </TableCell>
            <TableCell className={cn(NUMBER_CELL_CLASSES, 'font-semibold')}>
                {row.points}
            </TableCell>
        </TableRow>
    );
}

/**
 * Het klassement als tabel, gedeeld door beheer en deelnemer. Mobile-first:
 * onder `sm` staan korte koppen en blijft de spelernaam afbreken in plaats van
 * de tabel te laten scrollen. De volgorde, posities en gedeelde plaatsen komen
 * kant en klaar van de server.
 *
 * `viewerRow` is de eigen regel van de kijker die buiten `rows` valt (het
 * dashboard); `hasGap` zet er een scheidingsrij voor.
 */
export function StandingsTable({
    rows,
    viewerRow = null,
    hasGap = false,
}: {
    rows: StandingRow[];
    viewerRow?: StandingRow | null;
    hasGap?: boolean;
}) {
    const { t } = useTranslations();

    return (
        <div className="rounded-xl border">
            <Table aria-label={t('Standings')}>
                <TableHeader>
                    <TableRow>
                        <ColumnHead
                            short="#"
                            full={t('Position')}
                            className={cn(NUMBER_CELL_CLASSES, 'text-left')}
                        />
                        <ColumnHead short={t('Player')} full={t('Player')} />
                        <ColumnHead
                            short={t('Pld')}
                            full={t('Played')}
                            className={NUMBER_CELL_CLASSES}
                        />
                        <ColumnHead
                            short={t('W')}
                            full={t('Won')}
                            className={NUMBER_CELL_CLASSES}
                        />
                        <ColumnHead
                            short={t('L')}
                            full={t('Lost')}
                            className={NUMBER_CELL_CLASSES}
                        />
                        <ColumnHead
                            short={t('GD')}
                            full={t('Game difference')}
                            className={NUMBER_CELL_CLASSES}
                        />
                        <ColumnHead
                            short={t('Pts')}
                            full={t('Points')}
                            className={NUMBER_CELL_CLASSES}
                        />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((row) => (
                        <StandingTableRow key={row.player_id} row={row} />
                    ))}
                    {viewerRow && (
                        <>
                            {hasGap && (
                                <TableRow
                                    aria-hidden="true"
                                    className="hover:bg-transparent"
                                >
                                    <TableCell
                                        colSpan={COLUMN_COUNT}
                                        className="text-muted-foreground py-1 text-center leading-none"
                                    >
                                        ⋯
                                    </TableCell>
                                </TableRow>
                            )}
                            <StandingTableRow row={viewerRow} />
                        </>
                    )}
                </TableBody>
            </Table>
        </div>
    );
}

/**
 * Eén regel uitleg onder de stand: de puntentelling en de volgorde bij
 * gelijke punten.
 */
export function StandingsLegend({
    pointsSystem,
}: {
    pointsSystem: PointsSystemValue;
}): ReactNode {
    const { t } = useTranslations();

    return (
        <p className="text-muted-foreground text-sm">
            {t(
                'Points system: :system. With equal points the game difference decides first, then the head-to-head result; otherwise players share the place.',
                { system: pointsSystemLabel(pointsSystem, t) },
            )}
        </p>
    );
}

/**
 * Lege staat zolang er geen uitslag is. Bij een afgeronde competitie is er
 * niets meer om op te wachten, dus dan zonder uitleg.
 */
export function StandingsEmptyState({
    isFinished,
    size = 'default',
}: {
    isFinished: boolean;
    size?: 'default' | 'sm';
}) {
    const { t } = useTranslations();

    return (
        <EmptyState
            icon={Trophy}
            size={size}
            title={
                isFinished
                    ? t('No results were entered.')
                    : t('No results yet.')
            }
            description={
                isFinished
                    ? undefined
                    : t(
                          'The standings appear once the first result has been entered.',
                      )
            }
        />
    );
}

/**
 * Placeholder in dezelfde tabelvorm als de stand (rijen van dezelfde hoogte),
 * zodat het uitgesteld laden geen layout shift veroorzaakt.
 */
export function StandingsTableSkeleton({ rows = 5 }: { rows?: number }) {
    const { t } = useTranslations();

    return (
        <div role="status" aria-busy="true">
            <span className="sr-only">{t('Loading…')}</span>
            <div aria-hidden="true" className="divide-y rounded-xl border">
                <div className="flex h-10 items-center px-3">
                    <Skeleton className="h-4 w-24" />
                </div>
                {Array.from({ length: rows }, (_, index) => (
                    <div
                        key={index}
                        className="flex h-[3.0625rem] items-center justify-between gap-3 px-3"
                    >
                        <Skeleton className="h-4 w-5" />
                        <Skeleton className="h-4 flex-1" />
                        <Skeleton className="h-4 w-16" />
                    </div>
                ))}
            </div>
        </div>
    );
}
