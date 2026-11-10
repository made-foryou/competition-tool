import { usePage } from '@inertiajs/react';
import { Swords } from 'lucide-react';
import EmptyState from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { useTranslations } from '@/hooks/use-translations';
import { formatDateWithWeekday } from '@/lib/format-date';
import { cn } from '@/lib/utils';

export type ParticipantMatchState =
    | 'upcoming'
    | 'awaiting_result'
    | 'unscheduled'
    | 'not_played'
    | 'played';

export type ParticipantMatch = {
    id: number;
    opponent: string;
    opponent_is_participant: boolean;
    state: ParticipantMatchState;
    is_next: boolean;
    own_score: number | null;
    opponent_score: number | null;
    outcome: 'won' | 'lost' | null;
    match_day_date: string | null;
    field_name: string | null;
    starts_at: string | null;
    ends_at: string | null;
};

export type ParticipantMatchCounts = {
    total: number;
    open: number;
    unscheduled: number;
    not_played: number;
    played: number;
    won: number;
    lost: number;
};

export type ParticipantMatchOverview = {
    next_matches: ParticipantMatch[];
    counts: ParticipantMatchCounts;
};

export type ParticipantMatchListProps = {
    matches: ParticipantMatch[];
    counts: ParticipantMatchCounts;
};

/**
 * Eén regel voor dashboard en wedstrijdenpagina: er staan wedstrijden open en
 * geen enkele daarvan heeft al een plek.
 */
export function isNothingScheduled(counts: ParticipantMatchCounts): boolean {
    return counts.open > 0 && counts.unscheduled === counts.open;
}

/**
 * Mobile-first lijst van de eigen wedstrijden van een deelnemer. De rijen
 * komen gesorteerd van de server, inclusief `is_next`, en worden hier niet
 * opnieuw gesorteerd of afgeleid.
 */
export default function ParticipantMatchList({
    matches,
}: {
    matches: ParticipantMatch[];
}) {
    const { t } = useTranslations();
    const { locale } = usePage().props;

    return (
        <ul className="divide-y rounded-xl border">
            {matches.map((match) => {
                const showBadges =
                    match.is_next ||
                    match.state === 'awaiting_result' ||
                    !match.opponent_is_participant;

                return (
                    <li
                        key={match.id}
                        className={cn(
                            'flex items-start justify-between gap-3 p-3',
                            match.is_next && 'bg-muted/50',
                        )}
                    >
                        <div className="flex min-w-0 flex-1 flex-col gap-1">
                            <p className="font-medium [overflow-wrap:anywhere]">
                                <span className="sr-only">
                                    {t('Opponent')}:{' '}
                                </span>
                                {match.opponent}
                            </p>

                            {match.match_day_date && (
                                <p
                                    className={cn(
                                        'flex flex-wrap gap-x-3 gap-y-0.5 text-sm',
                                        match.is_next
                                            ? 'text-foreground font-medium'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    <span className="whitespace-nowrap">
                                        {formatDateWithWeekday(
                                            match.match_day_date,
                                            locale,
                                        )}
                                    </span>
                                    {match.starts_at && (
                                        <>
                                            <span
                                                className="whitespace-nowrap"
                                                aria-hidden="true"
                                            >
                                                {match.starts_at}
                                                {match.ends_at
                                                    ? ` – ${match.ends_at}`
                                                    : ''}
                                            </span>
                                            <span className="sr-only">
                                                {match.ends_at
                                                    ? t(':from to :until', {
                                                          from: match.starts_at,
                                                          until: match.ends_at,
                                                      })
                                                    : match.starts_at}
                                            </span>
                                            <span className="whitespace-nowrap">
                                                {match.field_name ??
                                                    t('No field')}
                                            </span>
                                        </>
                                    )}
                                </p>
                            )}

                            {match.state === 'unscheduled' && (
                                <p className="text-muted-foreground text-sm">
                                    {t('Not scheduled yet')}
                                </p>
                            )}

                            {showBadges && (
                                <div className="flex flex-wrap gap-1">
                                    {match.is_next && (
                                        <Badge variant="secondary">
                                            {t('Next match')}
                                        </Badge>
                                    )}
                                    {match.state === 'awaiting_result' && (
                                        <Badge variant="secondary">
                                            {t('Awaiting result')}
                                        </Badge>
                                    )}
                                    {!match.opponent_is_participant && (
                                        <Badge variant="outline">
                                            {t('No longer participating')}
                                        </Badge>
                                    )}
                                </div>
                            )}
                        </div>

                        {match.state === 'played' && (
                            <div className="flex shrink-0 flex-col items-end gap-1">
                                <span
                                    aria-hidden="true"
                                    className="font-semibold tabular-nums"
                                >
                                    {match.own_score} – {match.opponent_score}
                                </span>
                                <span className="sr-only">
                                    {t('Your score :own, opponent :opponent', {
                                        own: match.own_score ?? 0,
                                        opponent: match.opponent_score ?? 0,
                                    })}
                                </span>
                                {match.outcome === 'won' && (
                                    <Badge variant="default">{t('Won')}</Badge>
                                )}
                                {match.outcome === 'lost' && (
                                    <Badge variant="outline">{t('Lost')}</Badge>
                                )}
                            </div>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}

export function ParticipantMatchListSkeleton({ rows = 3 }: { rows?: number }) {
    const { t } = useTranslations();

    return (
        <div role="status" aria-busy="true">
            <span className="sr-only">{t('Loading…')}</span>
            <ul aria-hidden="true" className="divide-y rounded-xl border">
                {Array.from({ length: rows }, (_, index) => (
                    <li
                        key={index}
                        className="flex items-start justify-between gap-3 p-3"
                    >
                        <div className="min-w-0 space-y-1">
                            <div className="flex h-6 items-center">
                                <Skeleton className="h-4 w-32" />
                            </div>
                            <div className="flex h-5 items-center">
                                <Skeleton className="h-3 w-48" />
                            </div>
                        </div>
                        <div className="flex h-6 items-center">
                            <Skeleton className="h-5 w-16 rounded-md" />
                        </div>
                    </li>
                ))}
            </ul>
        </div>
    );
}

/**
 * Tellerregel onder de wedstrijden: gespeeld, gewonnen, verloren en, alleen
 * als die er zijn, niet gespeeld.
 */
export function MatchTally({ counts }: { counts: ParticipantMatchCounts }) {
    const { t } = useTranslations();

    let text: string;

    if (counts.played === 0 && counts.not_played === 0) {
        text = t('No results yet.');
    } else if (counts.not_played > 0) {
        text = t(
            'Played :played · Won :won · Lost :lost · Not played :not_played',
            {
                played: counts.played,
                won: counts.won,
                lost: counts.lost,
                not_played: counts.not_played,
            },
        );
    } else {
        text = t('Played :played · Won :won · Lost :lost', {
            played: counts.played,
            won: counts.won,
            lost: counts.lost,
        });
    }

    return <p className="text-muted-foreground text-sm">{text}</p>;
}

export function NoMatchesEmptyState({
    isFinished,
    size = 'default',
}: {
    isFinished: boolean;
    size?: 'default' | 'sm';
}) {
    const { t } = useTranslations();

    return isFinished ? (
        <EmptyState
            size={size}
            icon={Swords}
            title={t('You had no matches in this competition.')}
        />
    ) : (
        <EmptyState
            size={size}
            icon={Swords}
            title={t('No opponents yet.')}
            description={t(
                'Your matches appear here automatically once another participant joins.',
            )}
        />
    );
}
