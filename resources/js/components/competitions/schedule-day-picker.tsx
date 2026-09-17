import { usePage } from '@inertiajs/react';
import type { ScheduleMatchDayProps } from '@/components/competitions/schedule-panel';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/hooks/use-translations';
import { formatDate } from '@/lib/format-date';

type Props = {
    matchDays: ScheduleMatchDayProps[];
    selectedMatchDayId: number;
    onSelect: (matchDayId: number) => void;
};

/**
 * Kiest welke speeldag het grid toont. Bij precies één speeldag valt er niets
 * te kiezen: dan staat de datum met de openingstijden als gewone tekstregel
 * boven het grid, zodat de beheerder geen keuzeveld met één optie krijgt.
 */
export default function ScheduleDayPicker({
    matchDays,
    selectedMatchDayId,
    onSelect,
}: Props) {
    const { t } = useTranslations();
    const { locale } = usePage().props;

    if (matchDays.length === 0) {
        return null;
    }

    if (matchDays.length === 1) {
        const [matchDay] = matchDays;

        return (
            <p className="text-sm">
                {formatDate(matchDay.date, locale)}
                <span className="text-muted-foreground">
                    {' '}
                    · {matchDay.starts_at} – {matchDay.ends_at}
                </span>
            </p>
        );
    }

    return (
        <div className="grid gap-2">
            <Label htmlFor="schedule-match-day">{t('Match day')}</Label>
            <Select
                value={String(selectedMatchDayId)}
                onValueChange={(value) => onSelect(Number(value))}
            >
                <SelectTrigger
                    id="schedule-match-day"
                    className="w-full sm:w-80"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {matchDays.map((matchDay) => (
                        <SelectItem
                            key={matchDay.id}
                            value={String(matchDay.id)}
                        >
                            {formatDate(matchDay.date, locale)}
                            <span className="text-muted-foreground">
                                {' '}
                                · {matchDay.starts_at} – {matchDay.ends_at}
                            </span>
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
