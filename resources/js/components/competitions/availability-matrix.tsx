import { usePage } from '@inertiajs/react';
import { Check, ClipboardCheck, Minus } from 'lucide-react';
import type { MatchDayProps } from '@/components/competitions/match-day-form';
import EmptyState from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslations } from '@/hooks/use-translations';
import { formatDate } from '@/lib/format-date';
import { cn } from '@/lib/utils';

/**
 * Achtergrond op de sticky eerste kolom, zodat de horizontaal scrollende
 * inhoud er niet onderdoor schuift (ook niet in dark mode). De voettabel
 * gebruikt een effen `bg-muted` in plaats van de halftransparante
 * `bg-muted/50` van de rest van die rij, om dezelfde reden.
 */
const STICKY_COLUMN_CLASSES =
    'sticky left-0 z-10 bg-background group-hover:bg-muted';
const STICKY_FOOTER_COLUMN_CLASSES = 'sticky left-0 z-10 bg-muted';

export type AvailabilityRow = {
    id: number;
    name: string;
    submitted: boolean;
    match_day_ids: number[];
};

type Props = {
    matchDays: MatchDayProps[];
    availability: AvailabilityRow[];
    /** Springt naar de tab Speeldagen. Toont een CTA in de lege staat wanneer meegegeven. */
    onNavigateToMatchDays?: () => void;
    /** Springt naar de tab Deelnemers. Toont een CTA in de lege staat wanneer meegegeven. */
    onNavigateToParticipants?: () => void;
};

export default function AvailabilityMatrix({
    matchDays,
    availability,
    onNavigateToMatchDays,
    onNavigateToParticipants,
}: Props) {
    const { t } = useTranslations();
    const { locale } = usePage().props;

    if (matchDays.length === 0 || availability.length === 0) {
        return (
            <EmptyState
                icon={ClipboardCheck}
                title={t('No availability yet.')}
                description={t(
                    'Availability appears as soon as there are match days and participants.',
                )}
                action={
                    (onNavigateToMatchDays || onNavigateToParticipants) && (
                        <div className="flex gap-2">
                            {onNavigateToMatchDays && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={onNavigateToMatchDays}
                                >
                                    {t('Go to match days')}
                                </Button>
                            )}
                            {onNavigateToParticipants && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={onNavigateToParticipants}
                                >
                                    {t('Go to participants')}
                                </Button>
                            )}
                        </div>
                    )
                }
            />
        );
    }

    return (
        <section className="flex flex-col gap-4">
            <div className="rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead
                                scope="col"
                                className={cn('p-3', STICKY_COLUMN_CLASSES)}
                            >
                                {t('Name')}
                            </TableHead>
                            {matchDays.map((matchDay) => (
                                <TableHead
                                    key={matchDay.id}
                                    scope="col"
                                    className="p-3"
                                >
                                    <span className="block whitespace-nowrap">
                                        {formatDate(matchDay.date, locale)}
                                    </span>
                                    <span className="text-muted-foreground block font-normal whitespace-nowrap">
                                        {matchDay.starts_at} –{' '}
                                        {matchDay.ends_at}
                                    </span>
                                </TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {availability.map((row) => (
                            <TableRow key={row.id} className="group">
                                <TableHead
                                    scope="row"
                                    className={cn('p-3', STICKY_COLUMN_CLASSES)}
                                >
                                    {row.name}
                                    {!row.submitted && (
                                        <Badge
                                            variant="secondary"
                                            className="ml-2"
                                        >
                                            {t('Not filled in yet')}
                                        </Badge>
                                    )}
                                </TableHead>
                                {matchDays.map((matchDay) => {
                                    const isAvailable =
                                        row.match_day_ids.includes(matchDay.id);

                                    return (
                                        <TableCell
                                            key={matchDay.id}
                                            className="p-3"
                                        >
                                            {isAvailable ? (
                                                <Check className="size-4" />
                                            ) : (
                                                <Minus className="text-muted-foreground size-4" />
                                            )}
                                            <span className="sr-only">
                                                {isAvailable
                                                    ? t('Available')
                                                    : t('Not available')}
                                            </span>
                                        </TableCell>
                                    );
                                })}
                            </TableRow>
                        ))}
                    </TableBody>
                    <TableFooter>
                        <TableRow>
                            <TableCell
                                className={cn(
                                    'text-muted-foreground p-3',
                                    STICKY_FOOTER_COLUMN_CLASSES,
                                )}
                            >
                                {t('Available count')}
                            </TableCell>
                            {matchDays.map((matchDay) => (
                                <TableCell
                                    key={matchDay.id}
                                    className="p-3 font-medium"
                                >
                                    {
                                        availability.filter((row) =>
                                            row.match_day_ids.includes(
                                                matchDay.id,
                                            ),
                                        ).length
                                    }
                                </TableCell>
                            ))}
                        </TableRow>
                    </TableFooter>
                </Table>
            </div>
        </section>
    );
}
