import { usePage } from '@inertiajs/react';
import { CalendarIcon, XIcon } from 'lucide-react';
import * as React from 'react';
import { enUS, nl } from 'react-day-picker/locale';
import type { DayPickerLocale } from 'react-day-picker/locale';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useTranslations } from '@/hooks/use-translations';
import { formatDate, parseIsoDate } from '@/lib/format-date';
import { cn } from '@/lib/utils';

/** Maps the app's Laravel locale to a react-day-picker calendar locale. */
const CALENDAR_LOCALES: Record<string, DayPickerLocale> = {
    nl,
    en: enUS,
};

/** Bounds the year dropdown to a sensible, generously sized range. */
const DROPDOWN_START_YEAR = 2020;
const DROPDOWN_END_YEAR = new Date().getFullYear() + 5;

/** Formats a `Date` back to the `YYYY-MM-DD` string the backend expects. */
function toIsoDate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

type Props = {
    id?: string;
    name: string;
    /** `YYYY-MM-DD`, matching the native `<input type="date">` value this replaces. */
    defaultValue?: string | null;
    required?: boolean;
    /** Shows a button to unset the date, for nullable fields. */
    clearable?: boolean;
    disabled?: boolean;
    /** `YYYY-MM-DD`. Dates before this are disabled in the calendar. */
    minDate?: string | null;
    /** `YYYY-MM-DD`. Dates after this are disabled in the calendar. */
    maxDate?: string | null;
    className?: string;
    'aria-invalid'?: boolean;
    'aria-describedby'?: string;
};

/**
 * Date input styled as a shadcn button + popover calendar. Posts the same
 * `YYYY-MM-DD` (or empty string when cleared) payload as the native
 * `<input type="date">` it replaces, via a hidden input, so it works
 * unmodified with an Inertia `<Form>`.
 */
export function DatePicker({
    id,
    name,
    defaultValue,
    required = false,
    clearable = false,
    disabled = false,
    minDate,
    maxDate,
    className,
    'aria-invalid': ariaInvalid,
    'aria-describedby': ariaDescribedby,
}: Props) {
    const { t } = useTranslations();
    const { locale } = usePage().props;
    const [open, setOpen] = React.useState(false);
    const [selected, setSelected] = React.useState<Date | undefined>(
        defaultValue ? parseIsoDate(defaultValue) : undefined,
    );
    const hiddenInputRef = React.useRef<HTMLInputElement>(null);

    const value = selected ? toIsoDate(selected) : '';
    const calendarLocale = CALENDAR_LOCALES[locale] ?? nl;

    /**
     * Disables dates outside the given `minDate`/`maxDate` bounds. Each side
     * is its own matcher (rather than one combined `{ before, after }`
     * interval matcher) because an interval matches — and thus disables —
     * days *between* the two dates, which is the opposite of what we want.
     */
    const disabledMatchers = [
        ...(minDate ? [{ before: parseIsoDate(minDate) }] : []),
        ...(maxDate ? [{ after: parseIsoDate(maxDate) }] : []),
    ];

    /**
     * The Inertia `<Form>` component's `resetOnSuccess` resets native form
     * fields directly on the DOM and then dispatches a `reset` event on the
     * `<form>`, rather than going through this component's own state. Listen
     * for it so the visible picker resets along with the hidden input.
     */
    React.useEffect(() => {
        const form = hiddenInputRef.current?.form;

        if (!form) {
            return;
        }

        const handleReset = () => {
            setSelected(defaultValue ? parseIsoDate(defaultValue) : undefined);
        };

        form.addEventListener('reset', handleReset);

        return () => form.removeEventListener('reset', handleReset);
    }, [defaultValue]);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <input
                ref={hiddenInputRef}
                type="hidden"
                name={name}
                value={value}
            />
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    disabled={disabled}
                    aria-invalid={ariaInvalid}
                    aria-describedby={ariaDescribedby}
                    aria-required={required}
                    className={cn(
                        'w-full justify-start font-normal',
                        !selected && 'text-muted-foreground',
                        className,
                    )}
                >
                    <CalendarIcon />
                    {selected ? formatDate(value, locale) : t('Pick a date')}
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0" align="start">
                <Calendar
                    mode="single"
                    locale={calendarLocale}
                    weekStartsOn={1}
                    captionLayout="dropdown"
                    startMonth={new Date(DROPDOWN_START_YEAR, 0)}
                    endMonth={new Date(DROPDOWN_END_YEAR, 11)}
                    selected={selected}
                    defaultMonth={selected}
                    disabled={
                        disabledMatchers.length > 0
                            ? disabledMatchers
                            : undefined
                    }
                    onSelect={(date) => {
                        setSelected(date);
                        setOpen(false);
                    }}
                />
                {clearable && selected && (
                    <div className="border-t p-2">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="w-full"
                            onClick={() => {
                                setSelected(undefined);
                                setOpen(false);
                            }}
                        >
                            <XIcon />
                            {t('Clear')}
                        </Button>
                    </div>
                )}
            </PopoverContent>
        </Popover>
    );
}
