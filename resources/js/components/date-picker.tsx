import { usePage } from '@inertiajs/react';
import { CalendarIcon, XIcon } from 'lucide-react';
import * as React from 'react';
import { nl } from 'react-day-picker/locale';
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

    const value = selected ? toIsoDate(selected) : '';

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <input type="hidden" name={name} value={value} />
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
                    locale={nl}
                    weekStartsOn={1}
                    selected={selected}
                    defaultMonth={selected}
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
