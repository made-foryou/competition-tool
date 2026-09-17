import { usePage } from '@inertiajs/react';
import { Pin, Swords } from 'lucide-react';
import EmptyState from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslations } from '@/hooks/use-translations';
import { formatDate } from '@/lib/format-date';
import { matchStatusBadgeVariant, matchStatusLabel } from '@/lib/match-status';
import { pluralize } from '@/lib/plural';
import { schedulingFailureLabel } from '@/lib/scheduling-failure';
import { cn } from '@/lib/utils';

/**
 * Achtergrond op de sticky tabelkop, zodat wedstrijdrijen er bij verticaal
 * scrollen niet doorheen schemeren (ook niet in dark mode). Effen
 * `bg-background` in plaats van een halftransparante kleur, om dezelfde
 * reden als de sticky kolom in `availability-matrix.tsx`.
 */
const STICKY_HEADER_CLASSES = 'sticky top-0 z-10 bg-background';

export type MatchProps = {
    id: number;
    first_player: string;
    first_player_is_participant: boolean;
    second_player: string;
    second_player_is_participant: boolean;
    status: string;
    match_day_date: string | null;
    field_name: string | null;
    starts_at: string | null;
    ends_at: string | null;
    is_pinned: boolean;
    /** De reden waarom de planner deze wedstrijd niet kon plaatsen, `null` als hij nooit geprobeerd is. */
    scheduling_failure: string | null;
};

type Props = {
    matches: MatchProps[];
    /** Springt naar de tab Deelnemers. Toont een CTA in de lege staat wanneer meegegeven. */
    onNavigateToParticipants?: () => void;
};

export default function MatchList({
    matches,
    onNavigateToParticipants,
}: Props) {
    const { t } = useTranslations();
    const { locale } = usePage().props;

    if (matches.length === 0) {
        return (
            <EmptyState
                icon={Swords}
                title={t('No matches yet.')}
                description={t(
                    'Matches appear automatically once the competition has at least two participants.',
                )}
                action={
                    onNavigateToParticipants && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={onNavigateToParticipants}
                        >
                            {t('Go to participants')}
                        </Button>
                    )
                }
            />
        );
    }

    const playedCount = matches.filter(
        (match) => match.status === 'played',
    ).length;

    return (
        <section className="flex flex-col gap-4">
            <div className="flex flex-col gap-1">
                <h3 className="text-sm font-medium">{t('Matches')}</h3>
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-muted-foreground text-sm">
                        {t(
                            'The match list updates automatically whenever participants are added or removed.',
                        )}
                    </p>
                    <div className="flex items-center gap-2">
                        <Badge variant="outline">
                            {t(':played of :total played', {
                                played: playedCount,
                                total: matches.length,
                            })}
                        </Badge>
                        <Badge variant="secondary">
                            {pluralize(
                                t,
                                matches.length,
                                ':count match',
                                ':count matches',
                            )}
                        </Badge>
                    </div>
                </div>
            </div>

            <div className="rounded-xl border">
                <Table aria-label={t('Match list')}>
                    <TableHeader>
                        <TableRow>
                            <TableHead
                                scope="col"
                                className={cn('p-3', STICKY_HEADER_CLASSES)}
                            >
                                {t('Player 1')}
                            </TableHead>
                            <TableHead
                                scope="col"
                                className={cn('p-3', STICKY_HEADER_CLASSES)}
                            >
                                {t('Player 2')}
                            </TableHead>
                            <TableHead
                                scope="col"
                                className={cn('p-3', STICKY_HEADER_CLASSES)}
                            >
                                {t('Scheduled')}
                            </TableHead>
                            <TableHead
                                scope="col"
                                className={cn('p-3', STICKY_HEADER_CLASSES)}
                            >
                                {t('Status')}
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {matches.map((match) => (
                            <TableRow key={match.id}>
                                <TableCell className="p-3 whitespace-normal">
                                    {match.first_player}
                                    {!match.first_player_is_participant && (
                                        <Badge
                                            variant="outline"
                                            className="ml-2"
                                        >
                                            {t('No longer participating')}
                                        </Badge>
                                    )}
                                </TableCell>
                                <TableCell className="p-3 whitespace-normal">
                                    {match.second_player}
                                    {!match.second_player_is_participant && (
                                        <Badge
                                            variant="outline"
                                            className="ml-2"
                                        >
                                            {t('No longer participating')}
                                        </Badge>
                                    )}
                                </TableCell>
                                <TableCell className="p-3 align-top">
                                    {match.match_day_date ? (
                                        <div className="flex flex-col gap-0.5">
                                            <span className="flex items-center gap-1.5">
                                                {formatDate(
                                                    match.match_day_date,
                                                    locale,
                                                )}
                                                {match.is_pinned && (
                                                    <>
                                                        <Pin className="text-muted-foreground size-3.5" />
                                                        <span className="sr-only">
                                                            {t('Pinned')}
                                                        </span>
                                                    </>
                                                )}
                                            </span>
                                            <span className="text-muted-foreground text-xs">
                                                {match.starts_at} –{' '}
                                                {match.ends_at} ·{' '}
                                                {match.field_name ??
                                                    t('No field')}
                                            </span>
                                        </div>
                                    ) : (
                                        <div className="flex flex-col gap-0.5">
                                            <span className="text-muted-foreground">
                                                {t('Not scheduled')}
                                            </span>
                                            {match.scheduling_failure !==
                                                null && (
                                                <span className="text-muted-foreground text-xs">
                                                    {schedulingFailureLabel(
                                                        match.scheduling_failure,
                                                        t,
                                                    )}
                                                </span>
                                            )}
                                        </div>
                                    )}
                                </TableCell>
                                <TableCell className="p-3 align-top">
                                    <Badge
                                        variant={matchStatusBadgeVariant(
                                            match.status,
                                        )}
                                    >
                                        {matchStatusLabel(match.status, t)}
                                    </Badge>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </section>
    );
}
