import { Deferred, Head, Link, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    CirclePlay,
    type LucideIcon,
    Plus,
    Trophy,
    Users,
} from 'lucide-react';
import EmptyState from '@/components/empty-state';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useTranslations } from '@/hooks/use-translations';
import { competitionStatusLabel } from '@/lib/competition-status';
import { formatDate } from '@/lib/format-date';
import { pluralize } from '@/lib/plural';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import {
    create as createCompetition,
    edit as editCompetition,
    index as competitionsIndex,
} from '@/routes/competitions';
import { edit as editMatchDay } from '@/routes/competitions/match-days';

type Kpis = {
    competitions: number;
    active_competitions: number;
    participants: number;
    upcoming_match_days: number;
};

type UpcomingMatchDay = {
    id: number;
    date: string;
    starts_at: string;
    ends_at: string;
    fields_count: number;
    competition: { id: number; name: string };
};

type RecentCompetition = {
    id: number;
    name: string;
    status: string;
    participants_count: number;
};

type Props = {
    kpis: Kpis;
    /** Uitgesteld geladen, dus afwezig tijdens de eerste render. */
    upcomingMatchDays?: UpcomingMatchDay[];
    /** Uitgesteld geladen, dus afwezig tijdens de eerste render. */
    recentCompetitions?: RecentCompetition[];
};

export default function Dashboard({
    kpis,
    upcomingMatchDays,
    recentCompetitions,
}: Props) {
    const { t } = useTranslations();
    const { locale } = usePage().props;

    return (
        <>
            <Head title={t('Dashboard')} />
            <div className="mx-auto flex max-w-7xl flex-col gap-6 p-4">
                <Heading
                    as="h1"
                    title={t('Dashboard')}
                    description={t(
                        'An overview of your competitions, participants and match days.',
                    )}
                />

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <KpiCard
                        label={t('Total competitions')}
                        value={kpis.competitions}
                        icon={Trophy}
                        href={competitionsIndex().url}
                    />
                    <KpiCard
                        label={t('Active competitions')}
                        value={kpis.active_competitions}
                        icon={CirclePlay}
                        href={competitionsIndex().url}
                    />
                    <KpiCard
                        label={t('Unique participants')}
                        value={kpis.participants}
                        icon={Users}
                    />
                    <KpiCard
                        label={t('Upcoming match days')}
                        value={kpis.upcoming_match_days}
                        icon={CalendarDays}
                        href={competitionsIndex().url}
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="flex flex-col gap-3">
                        <h2 className="text-lg font-semibold">
                            {t('Upcoming match days')}
                        </h2>

                        <Deferred
                            data="upcomingMatchDays"
                            fallback={<ListSkeleton />}
                        >
                            {upcomingMatchDays?.length === 0 ? (
                                <EmptyState
                                    icon={CalendarDays}
                                    title={t('No upcoming match days.')}
                                    size="sm"
                                />
                            ) : (
                                <ul className="divide-y rounded-xl border">
                                    {upcomingMatchDays?.map((matchDay) => (
                                        <li
                                            key={matchDay.id}
                                            className="flex items-center justify-between gap-3 p-3"
                                        >
                                            <div className="min-w-0 space-y-1">
                                                <p className="truncate leading-6 font-medium">
                                                    <Link
                                                        href={editMatchDay([
                                                            matchDay.competition
                                                                .id,
                                                            matchDay.id,
                                                        ])}
                                                        className="hover:underline"
                                                    >
                                                        {formatDate(
                                                            matchDay.date,
                                                            locale,
                                                        )}
                                                    </Link>
                                                </p>
                                                <p className="text-muted-foreground truncate text-sm leading-5">
                                                    <Link
                                                        href={editCompetition(
                                                            matchDay.competition
                                                                .id,
                                                        )}
                                                        className="hover:underline"
                                                    >
                                                        {
                                                            matchDay.competition
                                                                .name
                                                        }
                                                    </Link>
                                                    {' · '}
                                                    {matchDay.starts_at} –{' '}
                                                    {matchDay.ends_at}
                                                </p>
                                            </div>
                                            <Badge
                                                variant="secondary"
                                                className="shrink-0"
                                            >
                                                {pluralize(
                                                    t,
                                                    matchDay.fields_count,
                                                    ':count field',
                                                    ':count fields',
                                                )}
                                            </Badge>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Deferred>
                    </section>

                    <section className="flex flex-col gap-3">
                        <h2 className="text-lg font-semibold">
                            {t('Recent competitions')}
                        </h2>

                        <Deferred
                            data="recentCompetitions"
                            fallback={<ListSkeleton />}
                        >
                            {recentCompetitions?.length === 0 ? (
                                <EmptyState
                                    icon={Trophy}
                                    title={t('No competitions yet.')}
                                    size="sm"
                                    action={
                                        <Button asChild size="sm">
                                            <Link href={createCompetition()}>
                                                <Plus />
                                                {t('New competition')}
                                            </Link>
                                        </Button>
                                    }
                                />
                            ) : (
                                <ul className="divide-y rounded-xl border">
                                    {recentCompetitions?.map((competition) => (
                                        <li
                                            key={competition.id}
                                            className="flex items-center justify-between gap-3 p-3"
                                        >
                                            <div className="min-w-0 space-y-1">
                                                <p className="truncate leading-6 font-medium">
                                                    <Link
                                                        href={editCompetition(
                                                            competition.id,
                                                        )}
                                                        className="hover:underline"
                                                    >
                                                        {competition.name}
                                                    </Link>
                                                </p>
                                                <p className="text-muted-foreground truncate text-sm leading-5">
                                                    {pluralize(
                                                        t,
                                                        competition.participants_count,
                                                        ':count participant',
                                                        ':count participants',
                                                    )}
                                                </p>
                                            </div>
                                            <Badge
                                                variant="secondary"
                                                className="shrink-0"
                                            >
                                                {competitionStatusLabel(
                                                    competition.status,
                                                    t,
                                                )}
                                            </Badge>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Deferred>
                    </section>
                </div>
            </div>
        </>
    );
}

function KpiCard({
    label,
    value,
    icon: Icon,
    href,
}: {
    label: string;
    value: number;
    icon: LucideIcon;
    /** Maakt de hele tegel klikbaar wanneer meegegeven. */
    href?: string;
}) {
    const content = (
        <>
            <CardHeader className="flex-row items-center justify-between gap-2 px-4">
                <CardDescription>{label}</CardDescription>
                <Icon className="text-muted-foreground size-4 shrink-0" />
            </CardHeader>
            <CardContent className="px-4">
                <p className="text-2xl font-semibold tabular-nums">{value}</p>
            </CardContent>
        </>
    );

    return (
        <Card
            className={cn(
                'gap-2 py-4',
                href &&
                    'hover:bg-muted/50 has-[:focus-visible]:ring-ring transition-colors has-[:focus-visible]:ring-2',
            )}
        >
            {href ? (
                <Link href={href} className="contents focus:outline-none">
                    {content}
                </Link>
            ) : (
                content
            )}
        </Card>
    );
}

/**
 * Placeholder met exact dezelfde rijhoogte als de geladen lijst, zodat het
 * uitgestelde laden geen layout shift veroorzaakt.
 */
function ListSkeleton({ rows = 5 }: { rows?: number }) {
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
                    className="flex items-center justify-between gap-3 p-3"
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

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
