import { Head, Link, router } from '@inertiajs/react';
import { FilterX, Plus, Search, Trophy } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { CompetitionProps } from '@/components/competitions/competition-form';
import EmptyState from '@/components/empty-state';
import Heading from '@/components/heading';
import { Pagination, type Paginated } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslations } from '@/hooks/use-translations';
import {
    COMPETITION_STATUSES,
    competitionStatusLabel,
} from '@/lib/competition-status';
import { create, edit, index } from '@/routes/competitions';

type CompetitionRow = CompetitionProps & { participants_count: number };

type Props = {
    competitions: Paginated<CompetitionRow>;
    filters: {
        search: string | null;
        status: string | null;
    };
};

/**
 * Radix Select accepteert geen lege waarde, dus "alle statussen" krijgt een
 * eigen sentinel die we niet meesturen naar de server.
 */
const ANY_STATUS = 'any';

const FILTER_PROPS = ['competitions', 'filters'];

const DEBOUNCE_MS = 300;

export default function CompetitionsIndex({ competitions, filters }: Props) {
    const { t } = useTranslations();

    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? ANY_STATUS);

    const isFirstRender = useRef(true);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            const trimmedSearch = search.trim();

            router.get(
                index().url,
                {
                    ...(trimmedSearch === '' ? {} : { search: trimmedSearch }),
                    ...(status === ANY_STATUS ? {} : { status }),
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: FILTER_PROPS,
                },
            );
        }, DEBOUNCE_MS);

        return () => clearTimeout(timeout);
    }, [search, status]);

    const hasActiveFilters = filters.search !== null || filters.status !== null;

    function resetFilters() {
        setSearch('');
        setStatus(ANY_STATUS);
    }

    return (
        <>
            <Head title={t('Competitions')} />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        as="h1"
                        title={t('Competitions')}
                        className="mb-0"
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus />
                            {t('New competition')}
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div className="relative sm:max-w-xs sm:flex-1">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('Search by name…')}
                            aria-label={t('Search competitions')}
                            maxLength={100}
                            className="pl-9"
                        />
                    </div>

                    <Select value={status} onValueChange={setStatus}>
                        <SelectTrigger
                            aria-label={t('Filter by status')}
                            className="w-full sm:w-48"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY_STATUS}>
                                {t('All statuses')}
                            </SelectItem>
                            {COMPETITION_STATUSES.map((value) => (
                                <SelectItem key={value} value={value}>
                                    {competitionStatusLabel(value, t)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {competitions.data.length === 0 ? (
                    hasActiveFilters || competitions.total > 0 ? (
                        <EmptyState
                            icon={Search}
                            title={t('No competitions match these filters.')}
                            action={
                                hasActiveFilters ? (
                                    <Button
                                        variant="outline"
                                        onClick={resetFilters}
                                    >
                                        <FilterX />
                                        {t('Clear filters')}
                                    </Button>
                                ) : undefined
                            }
                        />
                    ) : (
                        <EmptyState
                            icon={Trophy}
                            title={t('No competitions yet.')}
                            action={
                                <Button asChild>
                                    <Link href={create()}>
                                        <Plus />
                                        {t('New competition')}
                                    </Link>
                                </Button>
                            }
                        />
                    )
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="px-3">
                                        {t('Name')}
                                    </TableHead>
                                    <TableHead className="px-3">
                                        {t('Status')}
                                    </TableHead>
                                    <TableHead className="px-3">
                                        {t('Start date')}
                                    </TableHead>
                                    <TableHead className="px-3">
                                        {t('Participants')}
                                    </TableHead>
                                    <TableHead className="px-3 text-right">
                                        <span className="sr-only">
                                            {t('Actions')}
                                        </span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {competitions.data.map((competition) => (
                                    <TableRow key={competition.id}>
                                        <TableCell className="px-3">
                                            <Link
                                                href={edit(competition.id)}
                                                className="font-medium hover:underline"
                                            >
                                                {competition.name}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="px-3">
                                            <Badge variant="secondary">
                                                {competitionStatusLabel(
                                                    competition.status,
                                                    t,
                                                )}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="px-3">
                                            {competition.starts_at}
                                        </TableCell>
                                        <TableCell className="px-3">
                                            {competition.participants_count}
                                        </TableCell>
                                        <TableCell className="px-3 text-right">
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Link
                                                    href={edit(competition.id)}
                                                    aria-label={t(
                                                        'Edit :name',
                                                        {
                                                            name: competition.name,
                                                        },
                                                    )}
                                                >
                                                    {t('Edit')}
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <Pagination paginator={competitions} only={FILTER_PROPS} />
            </div>
        </>
    );
}

CompetitionsIndex.layout = {
    breadcrumbs: [{ title: 'Competitions', href: index() }],
};
