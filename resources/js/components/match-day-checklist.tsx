import { usePage } from '@inertiajs/react';
import { useTranslations } from '@/hooks/use-translations';
import { formatDate } from '@/lib/format-date';
import { cn } from '@/lib/utils';

export type MatchDayOption = {
    id: number;
    date: string;
    starts_at: string;
    ends_at: string;
    is_available?: boolean;
};

type Props = {
    matchDays: MatchDayOption[];
    /** De donkere console-stijl van de aanmeldpagina. */
    variant?: 'default' | 'console';
};

/**
 * Vinkjes voor de speeldagen waarop een deelnemer aanwezig kan zijn. Bewust
 * plain checkboxes: het omliggende formulier is uncontrolled, net als de
 * overige formulieren in deze applicatie.
 */
export default function MatchDayChecklist({
    matchDays,
    variant = 'default',
}: Props) {
    const { t } = useTranslations();
    const { locale } = usePage().props;
    const isConsole = variant === 'console';

    if (matchDays.length === 0) {
        return (
            <p
                className={cn(
                    'text-sm',
                    isConsole
                        ? 'text-console-text/65'
                        : 'text-muted-foreground',
                )}
            >
                {t('No match days have been planned yet.')}
            </p>
        );
    }

    return (
        <ul
            className={cn(
                'divide-y rounded-xl border',
                isConsole &&
                    'divide-console-input-border border-console-input-border',
            )}
        >
            {matchDays.map((matchDay) => (
                <li key={matchDay.id}>
                    <label className="flex cursor-pointer items-center gap-3 p-3">
                        <input
                            type="checkbox"
                            name="match_days[]"
                            value={matchDay.id}
                            defaultChecked={matchDay.is_available ?? false}
                            className="size-4 shrink-0"
                        />
                        <span className="min-w-0">
                            <span
                                className={cn(
                                    'block truncate font-medium',
                                    isConsole && 'text-console-text',
                                )}
                            >
                                {formatDate(matchDay.date, locale)}
                            </span>
                            <span
                                className={cn(
                                    'block truncate text-sm',
                                    isConsole
                                        ? 'text-console-text/65'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {matchDay.starts_at} – {matchDay.ends_at}
                            </span>
                        </span>
                    </label>
                </li>
            ))}
        </ul>
    );
}
