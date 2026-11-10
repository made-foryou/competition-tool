import { usePage } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { useTranslations } from '@/hooks/use-translations';
import { formatDateWithWeekday } from '@/lib/format-date';

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
    own_score: number | null;
    opponent_score: number | null;
    outcome: 'won' | 'lost' | null;
    match_day_date: string | null;
    field_name: string | null;
    starts_at: string | null;
    ends_at: string | null;
};

export type ParticipantMatchOverview = {
    next_matches: ParticipantMatch[];
    total_count: number;
    open_count: number;
    unscheduled_count: number;
    played_count: number;
    won_count: number;
    lost_count: number;
};

type Props = {
    matches: ParticipantMatch[];
    /** Markeert de eerste aankomende wedstrijd als "Eerstvolgende". */
    highlightNextMatch?: boolean;
};

/**
 * Mobile-first lijst van de eigen wedstrijden van een deelnemer. De rijen
 * komen al gesorteerd van de server en worden hier niet opnieuw gesorteerd.
 */
export default function ParticipantMatchList({
    matches,
    highlightNextMatch = false,
}: Props) {
    const { t } = useTranslations();
    const { locale } = usePage().props;

    const nextMatchId = highlightNextMatch
        ? matches.find((match) => match.state === 'upcoming')?.id
        : undefined;

    return (
        <ul className="divide-y rounded-xl border">
            {matches.map((match) => (
                <li
                    key={match.id}
                    className="flex items-start justify-between gap-3 p-3"
                >
                    <div className="flex min-w-0 flex-col gap-1">
                        <p className="flex flex-wrap items-center gap-x-2 gap-y-1 font-medium break-words">
                            <span>
                                <span className="sr-only">
                                    {t('Opponent')}:{' '}
                                </span>
                                {match.opponent}
                            </span>
                            {!match.opponent_is_participant && (
                                <Badge variant="outline">
                                    {t('No longer participating')}
                                </Badge>
                            )}
                        </p>
                        <p className="text-muted-foreground text-sm">
                            {match.match_day_date
                                ? [
                                      formatDateWithWeekday(
                                          match.match_day_date,
                                          locale,
                                      ),
                                      match.starts_at
                                          ? `${match.starts_at} – ${match.ends_at}`
                                          : null,
                                      match.starts_at
                                          ? (match.field_name ?? t('No field'))
                                          : null,
                                  ]
                                      .filter(Boolean)
                                      .join(' · ')
                                : t('Not scheduled yet')}
                        </p>
                    </div>

                    <div className="flex shrink-0 flex-col items-end gap-1">
                        {match.state === 'played' && (
                            <>
                                <span className="font-semibold tabular-nums">
                                    {match.own_score} – {match.opponent_score}
                                </span>
                                {match.outcome === 'won' && (
                                    <Badge variant="default">{t('Won')}</Badge>
                                )}
                                {match.outcome === 'lost' && (
                                    <Badge variant="outline">{t('Lost')}</Badge>
                                )}
                            </>
                        )}
                        {match.state === 'awaiting_result' && (
                            <Badge variant="outline">
                                {t('Awaiting result')}
                            </Badge>
                        )}
                        {match.id === nextMatchId && (
                            <Badge variant="secondary">{t('Next match')}</Badge>
                        )}
                    </div>
                </li>
            ))}
        </ul>
    );
}

export function ParticipantMatchListSkeleton({ rows = 3 }: { rows?: number }) {
    const { t } = useTranslations();

    return (
        <ul
            className="divide-y rounded-xl border"
            aria-label={t('Loading…')}
            aria-busy="true"
        >
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
    );
}
