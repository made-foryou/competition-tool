import { Deferred, Head, Link } from '@inertiajs/react';
import { Swords, Trophy } from 'lucide-react';
import EmptyState from '@/components/empty-state';
import ParticipantMatchList, {
    ParticipantMatchListSkeleton,
} from '@/components/participant-match-list';
import type { ParticipantMatch } from '@/components/participant-match-list';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import type { CompetitionStatusValue } from '@/lib/competition-status';
import { dashboard } from '@/routes/competition';

type Competition = {
    name: string;
    slug: string;
    status: CompetitionStatusValue;
};

type Props = {
    competition: Competition;
    isParticipant: boolean;
    matches?: ParticipantMatch[] | null;
};

export default function ParticipantMatches({
    competition,
    isParticipant,
    matches,
}: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('My matches')} />

            <div className="flex flex-col gap-4">
                <h1 className="flex items-center gap-2 text-lg font-semibold sm:text-xl">
                    <Swords className="size-5 shrink-0" />
                    {t('My matches')}
                </h1>

                {isParticipant ? (
                    <Deferred
                        data="matches"
                        fallback={<ParticipantMatchListSkeleton rows={5} />}
                    >
                        <MatchesContent
                            competition={competition}
                            matches={matches}
                        />
                    </Deferred>
                ) : (
                    <p role="status" className="text-muted-foreground text-sm">
                        {t(
                            'You are viewing this competition as an administrator. You do not take part yourself, so there are no matches of your own to show.',
                        )}
                    </p>
                )}

                <div>
                    <Button asChild variant="secondary" size="sm">
                        <Link href={dashboard(competition.slug)}>
                            {t('Back to your dashboard')}
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

function MatchesContent({
    competition,
    matches,
}: {
    competition: Competition;
    matches?: ParticipantMatch[] | null;
}) {
    const { t } = useTranslations();

    if (!matches) {
        return null;
    }

    if (matches.length === 0) {
        return (
            <EmptyState
                icon={Swords}
                title={t('No opponents yet.')}
                description={t(
                    'Your matches appear here automatically once another participant joins.',
                )}
            />
        );
    }

    const open = matches.filter(
        (match) =>
            match.state === 'upcoming' ||
            match.state === 'awaiting_result' ||
            match.state === 'unscheduled',
    );
    const notPlayed = matches.filter((match) => match.state === 'not_played');
    const played = matches.filter((match) => match.state === 'played');
    const isFinished = competition.status === 'finished';

    const playedSection = (
        <section className="flex flex-col gap-2">
            <h2 className="font-semibold">{t('Played')}</h2>
            {played.length === 0 ? (
                <EmptyState
                    size="sm"
                    icon={Trophy}
                    title={t('No results yet.')}
                    description={t(
                        'Results appear here once the organizer has entered them.',
                    )}
                />
            ) : (
                <>
                    <p className="text-muted-foreground text-sm">
                        {t('Your score is shown first.')}
                    </p>
                    <ParticipantMatchList matches={played} />
                </>
            )}
        </section>
    );

    return (
        <>
            {played.length > 0 && (
                <p className="text-muted-foreground text-sm">
                    {t('Played :played · Won :won · Lost :lost', {
                        played: played.length,
                        won: played.filter((match) => match.outcome === 'won')
                            .length,
                        lost: played.filter((match) => match.outcome === 'lost')
                            .length,
                    })}
                </p>
            )}

            {isFinished ? (
                <>
                    <p role="status" className="text-muted-foreground text-sm">
                        {t('This competition has finished.')}
                    </p>
                    {playedSection}
                    {notPlayed.length > 0 && (
                        <section className="flex flex-col gap-2">
                            <h2 className="font-semibold">{t('Not played')}</h2>
                            <ParticipantMatchList matches={notPlayed} />
                        </section>
                    )}
                </>
            ) : (
                <>
                    <section className="flex flex-col gap-2">
                        <h2 className="font-semibold">{t('To play')}</h2>
                        {open.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                {t('You have played all your matches.')}
                            </p>
                        ) : (
                            <>
                                {open.every(
                                    (match) => match.state === 'unscheduled',
                                ) && (
                                    <p className="text-muted-foreground text-sm">
                                        {t(
                                            'None of your matches have been scheduled yet. The organizer plans them on the match days.',
                                        )}
                                    </p>
                                )}
                                <ParticipantMatchList
                                    matches={open}
                                    highlightNextMatch
                                />
                            </>
                        )}
                    </section>
                    {playedSection}
                </>
            )}
        </>
    );
}
