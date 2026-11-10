import { Deferred, Head, Link, usePage } from '@inertiajs/react';
import {
    CalendarCheck,
    CalendarDays,
    MapPin,
    Swords,
    Users,
} from 'lucide-react';
import EmptyState from '@/components/empty-state';
import ParticipantMatchList, {
    ParticipantMatchListSkeleton,
} from '@/components/participant-match-list';
import type { ParticipantMatchOverview } from '@/components/participant-match-list';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import type { CompetitionStatusValue } from '@/lib/competition-status';
import { competitionStatusLabel } from '@/lib/competition-status';
import { formatDate } from '@/lib/format-date';
import { matches as matchesRoute } from '@/routes/competition';
import { edit as editAvailability } from '@/routes/competition/availability';

type Props = {
    competition: {
        name: string;
        slug: string;
        status: CompetitionStatusValue;
        description: string | null;
        location: string | null;
        starts_at: string;
        ends_at: string | null;
    };
    participants: Array<{ id: number; name: string }>;
    availableMatchDays: number;
    totalMatchDays: number;
    isParticipant: boolean;
    matchOverview?: ParticipantMatchOverview | null;
};

export default function ParticipantDashboard({
    competition,
    participants,
    availableMatchDays,
    totalMatchDays,
    isParticipant,
}: Props) {
    const { t } = useTranslations();
    const { locale } = usePage().props;

    return (
        <>
            <Head title={competition.name} />

            <div className="flex flex-col gap-4">
                <section className="flex flex-col gap-2 rounded-xl border p-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="text-lg font-semibold sm:text-xl">
                            {competition.name}
                        </h1>
                        <Badge variant="secondary">
                            {competitionStatusLabel(competition.status, t)}
                        </Badge>
                    </div>

                    <p className="text-muted-foreground flex items-center gap-2 text-sm">
                        <CalendarDays className="size-4 shrink-0" />
                        {competition.ends_at
                            ? t(':from until :until', {
                                  from: formatDate(
                                      competition.starts_at,
                                      locale,
                                  ),
                                  until: formatDate(
                                      competition.ends_at,
                                      locale,
                                  ),
                              })
                            : formatDate(competition.starts_at, locale)}
                    </p>

                    {competition.location && (
                        <p className="text-muted-foreground flex items-center gap-2 text-sm">
                            <MapPin className="size-4 shrink-0" />
                            {competition.location}
                        </p>
                    )}

                    {competition.description && (
                        <p className="mt-2 text-sm leading-relaxed">
                            {competition.description}
                        </p>
                    )}
                </section>

                <section className="flex flex-col gap-3 rounded-xl border p-4">
                    <h2 className="flex items-center gap-2 font-semibold">
                        <Swords className="size-4" />
                        {t('My matches')}
                    </h2>
                    {isParticipant ? (
                        <Deferred
                            data="matchOverview"
                            fallback={<ParticipantMatchListSkeleton />}
                        >
                            <MatchOverview competition={competition} />
                        </Deferred>
                    ) : (
                        <p className="text-muted-foreground text-sm">
                            {t(
                                'You are viewing this competition as an administrator. You do not take part yourself, so there are no matches of your own to show.',
                            )}
                        </p>
                    )}
                </section>

                {totalMatchDays > 0 && (
                    <section className="flex flex-col gap-3 rounded-xl border p-4">
                        <h2 className="flex items-center gap-2 font-semibold">
                            <CalendarCheck className="size-4" />
                            {t('My availability')}
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            {t('Available on :count of :total match days', {
                                count: availableMatchDays,
                                total: totalMatchDays,
                            })}
                        </p>
                        <div>
                            <Button asChild variant="secondary" size="sm">
                                <Link href={editAvailability(competition.slug)}>
                                    {competition.status === 'active'
                                        ? t('Change availability')
                                        : t('View availability')}
                                </Link>
                            </Button>
                        </div>
                    </section>
                )}

                <section className="flex flex-col gap-3 rounded-xl border p-4">
                    <h2 className="flex items-center gap-2 font-semibold">
                        <Users className="size-4" />
                        {t('Participants')}
                        <span className="text-muted-foreground text-sm font-normal">
                            ({participants.length})
                        </span>
                    </h2>
                    {participants.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            {t('No participants yet.')}
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {participants.map((participant) => (
                                <li
                                    key={participant.id}
                                    className="py-2 text-sm"
                                >
                                    {participant.name}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

function MatchOverview({ competition }: { competition: Props['competition'] }) {
    const { t } = useTranslations();
    const { matchOverview } = usePage<{
        matchOverview?: ParticipantMatchOverview | null;
    }>().props;

    if (!matchOverview) {
        return null;
    }

    if (matchOverview.total_count === 0) {
        return (
            <EmptyState
                size="sm"
                icon={Swords}
                title={t('No opponents yet.')}
                description={t(
                    'Your matches appear here automatically once another participant joins.',
                )}
            />
        );
    }

    const isFinished = competition.status === 'finished';
    const hasPlayedAll = matchOverview.open_count === 0;

    return (
        <>
            {isFinished ? (
                <p className="text-muted-foreground text-sm">
                    {t('This competition has finished.')}
                </p>
            ) : hasPlayedAll ? (
                <p className="text-sm">
                    {t('You have played all your matches.')}
                </p>
            ) : (
                <>
                    {matchOverview.unscheduled_count ===
                        matchOverview.open_count && (
                        <p className="text-muted-foreground text-sm">
                            {t(
                                'None of your matches have been scheduled yet. The organizer plans them on the match days.',
                            )}
                        </p>
                    )}
                    <ParticipantMatchList
                        matches={matchOverview.next_matches}
                        highlightNextMatch
                    />
                </>
            )}

            <p className="text-muted-foreground text-sm">
                {matchOverview.played_count > 0
                    ? t('Played :played · Won :won · Lost :lost', {
                          played: matchOverview.played_count,
                          won: matchOverview.won_count,
                          lost: matchOverview.lost_count,
                      })
                    : t('No results yet.')}
            </p>

            <div>
                <Button asChild variant="secondary" size="sm">
                    <Link href={matchesRoute(competition.slug)} prefetch>
                        {t('View all matches (:count)', {
                            count: matchOverview.total_count,
                        })}
                    </Link>
                </Button>
            </div>
        </>
    );
}
