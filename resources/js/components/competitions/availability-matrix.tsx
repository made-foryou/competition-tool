import { usePage } from '@inertiajs/react';
import { Check, Minus } from 'lucide-react';
import type { MatchDayProps } from '@/components/competitions/match-day-form';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/hooks/use-translations';
import { formatDate } from '@/lib/format-date';

export type AvailabilityRow = {
    id: number;
    name: string;
    submitted: boolean;
    match_day_ids: number[];
};

type Props = {
    matchDays: MatchDayProps[];
    availability: AvailabilityRow[];
};

export default function AvailabilityMatrix({ matchDays, availability }: Props) {
    const { t } = useTranslations();
    const { locale } = usePage().props;

    if (matchDays.length === 0 || availability.length === 0) {
        return (
            <p className="text-muted-foreground text-sm">
                {t(
                    'Availability appears as soon as there are match days and participants.',
                )}
            </p>
        );
    }

    return (
        <section className="flex flex-col gap-4">
            <div className="overflow-x-auto rounded-xl border">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b text-left">
                            <th className="p-3">{t('Name')}</th>
                            {matchDays.map((matchDay) => (
                                <th key={matchDay.id} className="p-3">
                                    <span className="block whitespace-nowrap">
                                        {formatDate(matchDay.date, locale)}
                                    </span>
                                    <span className="text-muted-foreground block font-normal whitespace-nowrap">
                                        {matchDay.starts_at} –{' '}
                                        {matchDay.ends_at}
                                    </span>
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {availability.map((row) => (
                            <tr key={row.id} className="border-b last:border-0">
                                <td className="p-3">
                                    <span className="font-medium">
                                        {row.name}
                                    </span>
                                    {!row.submitted && (
                                        <Badge
                                            variant="secondary"
                                            className="ml-2"
                                        >
                                            {t('Not filled in yet')}
                                        </Badge>
                                    )}
                                </td>
                                {matchDays.map((matchDay) => {
                                    const isAvailable =
                                        row.match_day_ids.includes(matchDay.id);

                                    return (
                                        <td key={matchDay.id} className="p-3">
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
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr className="border-t">
                            <td className="text-muted-foreground p-3">
                                {t('Available')}
                            </td>
                            {matchDays.map((matchDay) => (
                                <td
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
                                </td>
                            ))}
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    );
}
