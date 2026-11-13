import { Deferred, Head, Link, usePage } from '@inertiajs/react';
import {
    CalendarCheck,
    CalendarDays,
    MapPin,
    Swords,
    Trophy,
    Users,
} from 'lucide-react';
import ParticipantMatchList, {
    isNothingScheduled,
    MatchTally,
    NoMatchesEmptyState,
    ParticipantMatchListSkeleton,
} from '@/components/participant-match-list';
import type { ParticipantMatchOverview } from '@/components/participant-match-list';
import {
    StandingsEmptyState,
    StandingsLegend,
    StandingsTable,
    StandingsTableSkeleton,
} from '@/components/standings-table';
import type { StandingsSummary } from '@/components/standings-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import type { CompetitionStatusValue } from '@/lib/competition-status';
import { competitionStatusLabel } from '@/lib/competition-status';
import { formatDate } from '@/lib/format-date';
import {
    matches as matchesRoute,
    standings as standingsRoute,
} from '@/routes/competition';
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
    standingsSummary?: StandingsSummary;
};

export default function ParticipantDashboard({
    competition,
    participants,
    availableMatchDays,
    totalMatchDays,
    isParticipant,
    matchOverview,
    standingsSummary,
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
                            <MatchOverview
                                competition={competition}
                                overview={matchOverview}
                            />
                        </Deferred>
                    ) : (
                        <p className="text-muted-foreground text-sm">
                            {t(
                                'You are viewing this competition as an administrator and do not take part yourself, so you have no matches of your own.',
                            )}
                        </p>
                    )}
                </section>

                <section className="flex flex-col gap-3 rounded-xl border p-4">
                    <h2 className="flex items-center gap-2 font-semibold">
                        <Trophy className="size-4" />
                        {t('Standings')}
                    </h2>
                    <Deferred
                        data="standingsSummary"
                        fallback={<StandingsTableSkeleton rows={3} />}
                    >
                        <StandingsOverview
                            competition={competition}
                            summary={standingsSummary}
                        />
                    </Deferred>
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
                            <Button
                                asChild
                                variant="secondary"
                                size="touch"
                                className="w-full sm:w-auto"
                            >
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

function MatchOverview({
    competition,
    overview,
}: {
    competition: Props['competition'];
    overview?: ParticipantMatchOverview | null;
}) {
    const { t } = useTranslations();

    if (!overview) {
        return null;
    }

    const { counts } = overview;
    const isFinished = competition.status === 'finished';

    if (counts.total === 0) {
        return <NoMatchesEmptyState isFinished={isFinished} size="sm" />;
    }

    const viewAll = (
        <div>
            <Button
                asChild
                variant="secondary"
                size="touch"
                className="w-full sm:w-auto"
            >
                <Link href={matchesRoute(competition.slug)} prefetch>
                    {t('View all matches (:count)', { count: counts.total })}
                </Link>
            </Button>
        </div>
    );

    if (isFinished) {
        return (
            <>
                <p className="text-muted-foreground text-sm">
                    {t('This competition has finished.')}
                </p>
                <MatchTally counts={counts} />
                {viewAll}
            </>
        );
    }

    if (counts.open === 0) {
        return (
            <>
                <p className="text-sm">
                    {t('You have played all your matches.')}
                </p>
                <MatchTally counts={counts} />
                {viewAll}
            </>
        );
    }

    return (
        <>
            {isNothingScheduled(counts) && (
                <p className="text-muted-foreground text-sm">
                    {t(
                        'None of your matches have been scheduled yet. The organizer plans them on the match days.',
                    )}
                </p>
            )}
            <ParticipantMatchList matches={overview.next_matches} />
            <MatchTally counts={counts} />
            {viewAll}
        </>
    );
}

function StandingsOverview({
    competition,
    summary,
}: {
    competition: Props['competition'];
    summary?: StandingsSummary;
}) {
    const { t } = useTranslations();

    if (!summary) {
        return null;
    }

    if (summary.total === 0) {
        return (
            <StandingsEmptyState
                isFinished={competition.status === 'finished'}
                size="sm"
            />
        );
    }

    return (
        <>
            <StandingsTable
                rows={summary.leaders}
                viewerRow={summary.viewer_row}
                hasGap={summary.has_gap}
            />
            <StandingsLegend pointsSystem={summary.points_system} />
            <div>
                <Button
                    asChild
                    variant="secondary"
                    size="touch"
                    className="w-full sm:w-auto"
                >
                    <Link href={standingsRoute(competition.slug)} prefetch>
                        {t('View the full standings (:count)', {
                            count: summary.total,
                        })}
                    </Link>
                </Button>
            </div>
        </>
    );
}
