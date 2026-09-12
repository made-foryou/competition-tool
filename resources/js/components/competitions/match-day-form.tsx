import { Form } from '@inertiajs/react';
import { DatePicker } from '@/components/date-picker';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';

export type MatchDayProps = {
    id: number;
    date: string;
    starts_at: string;
    ends_at: string;
};

type Props = {
    matchDay?: MatchDayProps;
    action: string;
    method: 'post' | 'put';
    submitLabel: string;
    /** Toont het veld waarmee de speelvelden bij het aanmaken gegenereerd worden. */
    withFieldCount?: boolean;
    resetOnSuccess?: boolean;
    /** `YYYY-MM-DD`. Bounds the date picker to the competition's period. */
    competitionStartsAt?: string;
    /** `YYYY-MM-DD`. Bounds the date picker to the competition's period. */
    competitionEndsAt?: string | null;
    className?: string;
};

export default function MatchDayForm({
    matchDay,
    action,
    method,
    submitLabel,
    withFieldCount = false,
    resetOnSuccess = false,
    competitionStartsAt,
    competitionEndsAt,
    className = 'flex max-w-xl flex-col gap-6',
}: Props) {
    const { t } = useTranslations();

    return (
        <Form
            action={action}
            method={method}
            resetOnSuccess={resetOnSuccess}
            className={className}
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="date">{t('Date')}</Label>
                            <DatePicker
                                id="date"
                                name="date"
                                required
                                defaultValue={matchDay?.date}
                                minDate={competitionStartsAt}
                                maxDate={competitionEndsAt}
                                aria-invalid={!!errors.date}
                                aria-describedby={
                                    errors.date ? 'date-error' : undefined
                                }
                            />
                            <InputError id="date-error" message={errors.date} />
                        </div>

                        {withFieldCount && (
                            <div className="grid gap-2">
                                <Label htmlFor="field_count">
                                    {t('Number of fields')}
                                </Label>
                                <Input
                                    id="field_count"
                                    name="field_count"
                                    type="number"
                                    min={1}
                                    max={20}
                                    required
                                    defaultValue={4}
                                    aria-invalid={!!errors.field_count}
                                    aria-describedby={
                                        errors.field_count
                                            ? 'field_count-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="field_count-error"
                                    message={errors.field_count}
                                />
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="starts_at">{t('Start time')}</Label>
                            <Input
                                id="starts_at"
                                name="starts_at"
                                type="time"
                                required
                                defaultValue={matchDay?.starts_at ?? ''}
                                aria-invalid={!!errors.starts_at}
                                aria-describedby={
                                    errors.starts_at
                                        ? 'starts_at-error'
                                        : undefined
                                }
                            />
                            <InputError
                                id="starts_at-error"
                                message={errors.starts_at}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="ends_at">{t('End time')}</Label>
                            <Input
                                id="ends_at"
                                name="ends_at"
                                type="time"
                                required
                                defaultValue={matchDay?.ends_at ?? ''}
                                aria-invalid={!!errors.ends_at}
                                aria-describedby={
                                    errors.ends_at ? 'ends_at-error' : undefined
                                }
                            />
                            <InputError
                                id="ends_at-error"
                                message={errors.ends_at}
                            />
                        </div>
                    </div>

                    <div>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {submitLabel}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
