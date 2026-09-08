import { Head } from '@inertiajs/react';
import { useTranslations } from '@/hooks/use-translations';

export default function NoCompetition() {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('No active competition')} />
            <div className="flex flex-col items-center gap-2 py-16 text-center">
                <h1 className="text-lg font-semibold">
                    {t('No active competition')}
                </h1>
                <p className="text-muted-foreground max-w-sm text-sm">
                    {t(
                        'You are not linked to an active competition right now. Contact the organizer if you expected one.',
                    )}
                </p>
            </div>
        </>
    );
}
