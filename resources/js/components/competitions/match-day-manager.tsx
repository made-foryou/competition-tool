import { Form, Link, usePage } from '@inertiajs/react';
import type { MatchDayProps } from '@/components/competitions/match-day-form';
import MatchDayForm from '@/components/competitions/match-day-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { formatDate } from '@/lib/format-date';
import {
    destroy as destroyMatchDay,
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
        <section className="flex max-w-xl flex-col gap-4">
            <h2 className="text-lg font-semibold">{t('Match days')}</h2>

            {matchDays.length === 0 ? (
                <p className="text-muted-foreground text-sm">
                    {t('No match days yet.')}
                </p>
            ) : (
                <ul className="divide-y rounded-xl border">
                    {matchDays.map((matchDay) => (
                        <li
                            key={matchDay.id}
                            className="flex items-center justify-between gap-2 p-3"
                        >
                            <div className="min-w-0">
                                <p className="truncate font-medium">
                                    {formatDate(matchDay.date, locale)}
                                </p>
                                <p className="text-muted-foreground truncate text-sm">
                                    {matchDay.starts_at} – {matchDay.ends_at}
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
                                <Form
                                    action={
                                        destroyMatchDay([
                                            competitionId,
                                            matchDay.id,
                                        ]).url
                                    }
                                    method="delete"
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="ghost"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            {t('Remove')}
                                        </Button>
                                    )}
                                </Form>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <MatchDayForm
                action={storeMatchDay(competitionId).url}
                method="post"
                withFieldCount
                resetOnSuccess
                submitLabel={t('Add match day')}
                className="flex flex-col gap-4 rounded-xl border p-4"
            />
        </section>
    );
}
