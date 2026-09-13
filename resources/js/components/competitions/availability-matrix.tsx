import { usePage } from '@inertiajs/react';
import { BellRing, Check, ClipboardCheck, Download, Minus } from 'lucide-react';
import CompetitionAvailabilityExportController from '@/actions/App/Http/Controllers/CompetitionAvailabilityExportController';
import CompetitionAvailabilityReminderController from '@/actions/App/Http/Controllers/CompetitionAvailabilityReminderController';
import type { MatchDayProps } from '@/components/competitions/match-day-form';
import ConfirmDialog from '@/components/confirm-dialog';
import EmptyState from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
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
 * Formatteert een volledige ISO-8601-timestamp (datum + tijd + offset) zoals
 * `available_at`. In tegenstelling tot `formatDate` hoeft hier niet handmatig
 * op lokale middernacht geparsed te worden: de timestamp bevat al een
 * tijdzone-offset, dus `new Date()` geeft geen dagverschuiving.
 */
function formatDateTime(isoDateTime: string, locale: string): string {
    return new Intl.DateTimeFormat(locale, {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(isoDateTime));
}

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

export type AvailabilityReminderProps = {
    pending_count: number;
    can_send: boolean;
    available_at: string | null;
};

type Props = {
    competitionId: number;
    matchDays: MatchDayProps[];
    availability: AvailabilityRow[];
    reminder: AvailabilityReminderProps;
    /** Springt naar de tab Speeldagen. Toont een CTA in de lege staat wanneer meegegeven. */
    onNavigateToMatchDays?: () => void;
    /** Springt naar de tab Deelnemers. Toont een CTA in de lege staat wanneer meegegeven. */
    onNavigateToParticipants?: () => void;
};

export default function AvailabilityMatrix({
    competitionId,
    matchDays,
    availability,
    reminder,
    onNavigateToMatchDays,
    onNavigateToParticipants,
}: Props) {
    const { t } = useTranslations();
    const { locale } = usePage().props;

    const reminderDisabledReason = reminder.can_send
        ? null
        : reminder.pending_count === 0
          ? t('Everyone has filled in their availability.')
          : reminder.available_at !== null
            ? t(
                  'A reminder was already sent. You can send a new one from :time.',
                  { time: formatDateTime(reminder.available_at, locale) },
              )
            : t('Reminders can only be sent for an active competition.');

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
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-muted-foreground text-sm">
                    {reminderDisabledReason}
                </p>
                <div className="flex flex-wrap gap-2">
                    {reminder.can_send ? (
                        <ConfirmDialog
                            trigger={
                                <Button variant="outline" size="sm">
                                    <BellRing />
                                    {t('Send reminder')}
                                </Button>
                            }
                            title={t('Send reminder?')}
                            description={t(
                                'This emails the :count participants who have not filled in their availability yet.',
                                { count: reminder.pending_count },
                            )}
                            action={CompetitionAvailabilityReminderController.form(
                                competitionId,
                            )}
                            confirmLabel={t('Send reminder')}
                            confirmVariant="default"
                        />
                    ) : (
                        <Button variant="outline" size="sm" disabled>
                            <BellRing />
                            {t('Send reminder')}
                        </Button>
                    )}
                    <a
                        href={CompetitionAvailabilityExportController.url(
                            competitionId,
                        )}
                        download
                        className={cn(
                            buttonVariants({ variant: 'outline', size: 'sm' }),
                        )}
                    >
                        <Download />
                        {t('Export CSV')}
                    </a>
                </div>
            </div>

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
