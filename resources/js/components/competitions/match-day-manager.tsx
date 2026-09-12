import { Link, usePage } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import MatchDayController from '@/actions/App/Http/Controllers/MatchDayController';
import type { MatchDayProps } from '@/components/competitions/match-day-form';
import MatchDayForm from '@/components/competitions/match-day-form';
import ConfirmDialog from '@/components/confirm-dialog';
import EmptyState from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { formatDate } from '@/lib/format-date';
import {
    edit as editMatchDay,
    store as storeMatchDay,
} from '@/routes/competitions/match-days';

export type MatchDayListItem = MatchDayProps & { fields_count: number };

type Props = {
    competitionId: number;
    matchDays: MatchDayListItem[];
};

export default function MatchDayManager({ competitionId, matchDays }: Props) {
    const { t } = useTranslations();
    const { locale } = usePage().props;

    return (
        <section className="flex flex-col gap-4">
            {matchDays.length === 0 ? (
                <EmptyState
                    icon={CalendarDays}
                    title={t('No match days yet.')}
                    description={t('Add the first match day below.')}
                />
            ) : (
                <ul className="divide-y rounded-xl border">
                    {matchDays.map((matchDay) => {
                        const formattedDate = formatDate(matchDay.date, locale);

                        return (
                            <li
                                key={matchDay.id}
                                className="flex items-center justify-between gap-2 p-3"
                            >
                                <div className="min-w-0">
                                    <p className="truncate font-medium">
                                        {formattedDate}
                                    </p>
                                    <p className="text-muted-foreground truncate text-sm">
                                        {matchDay.starts_at} –{' '}
                                        {matchDay.ends_at}
                                    </p>
                                </div>
                                <div className="flex shrink-0 items-center gap-2">
                                    <Badge variant="secondary">
                                        {matchDay.fields_count} {t('Fields')}
                                    </Badge>
                                    <Button asChild variant="ghost" size="sm">
                                        <Link
                                            href={editMatchDay([
                                                competitionId,
                                                matchDay.id,
                                            ])}
                                        >
                                            {t('Edit')}
                                        </Link>
                                    </Button>
                                    <ConfirmDialog
                                        trigger={
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="text-destructive-foreground"
                                                aria-label={t(
                                                    'Remove match day :date',
                                                    { date: formattedDate },
                                                )}
                                            >
                                                {t('Remove')}
                                            </Button>
                                        }
                                        title={t('Remove match day?')}
                                        description={t(
                                            'This removes the match day of :date, including its fields and availability.',
                                            { date: formattedDate },
                                        )}
                                        action={MatchDayController.destroy.form(
                                            [competitionId, matchDay.id],
                                        )}
                                        confirmLabel={t('Remove match day')}
                                    />
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}

            <MatchDayForm
                action={storeMatchDay(competitionId).url}
                method="post"
                withFieldCount
                resetOnSuccess
                submitLabel={t('Add match day')}
                className="flex max-w-xl flex-col gap-4 rounded-xl border p-4"
            />
        </section>
    );
}
