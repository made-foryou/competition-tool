import { Form, Head } from '@inertiajs/react';
import { CalendarCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import type { MatchDayOption } from '@/components/match-day-checklist';
import MatchDayChecklist from '@/components/match-day-checklist';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { update } from '@/routes/competition/availability';

type Props = {
    competition: { name: string; slug: string };
    matchDays: MatchDayOption[];
    hasSubmitted: boolean;
};

export default function ParticipantAvailability({
    competition,
    matchDays,
    hasSubmitted,
}: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('My availability')} />

            <div className="flex flex-col gap-4">
                <div className="flex flex-col gap-1">
                    <h1 className="flex items-center gap-2 text-lg font-semibold sm:text-xl">
                        <CalendarCheck className="size-5 shrink-0" />
                        {t('My availability')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {hasSubmitted
                            ? t('You can change this at any time.')
                            : t('Fill in your availability to continue.')}
                    </p>
                </div>

                <Form
                    {...update.form({ competition: competition.slug })}
                    options={{ preserveScroll: true }}
                    className="flex flex-col gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <MatchDayChecklist matchDays={matchDays} />
                            <InputError message={errors.match_days} />

                            {matchDays.length > 0 && (
                                <p className="text-muted-foreground text-sm">
                                    {t(
                                        'Leave everything unchecked if you cannot attend any match day.',
                                    )}
                                </p>
                            )}

                            <div>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {t('Save availability')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
