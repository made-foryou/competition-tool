import { Deferred, Head, Link } from '@inertiajs/react';
import { Trophy } from 'lucide-react';
import {
    StandingsEmptyState,
    StandingsLegend,
    StandingsTable,
    StandingsTableSkeleton,
} from '@/components/standings-table';
import type { StandingsProps } from '@/components/standings-table';
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
    standings?: StandingsProps;
};

export default function ParticipantStandings({
    competition,
    standings,
}: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('Standings')} />

            <div className="flex flex-col gap-4">
                <h1 className="flex items-center gap-2 text-lg font-semibold sm:text-xl">
                    <Trophy className="size-5 shrink-0" />
                    {t('Standings')}
                </h1>

                <Deferred
                    data="standings"
                    fallback={<StandingsTableSkeleton rows={8} />}
                >
                    <StandingsContent
                        competition={competition}
                        standings={standings}
                    />
                </Deferred>

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

function StandingsContent({
    competition,
    standings,
}: {
    competition: Competition;
    standings?: StandingsProps;
}) {
    if (!standings) {
        return null;
    }

    if (standings.rows.length === 0) {
        return (
            <StandingsEmptyState
                isFinished={competition.status === 'finished'}
            />
        );
    }

    return (
        <>
            <StandingsTable rows={standings.rows} />
            <StandingsLegend pointsSystem={standings.points_system} />
        </>
    );
}
