import { Head } from '@inertiajs/react';
import CompetitionForm from '@/components/competitions/competition-form';
import { useTranslations } from '@/hooks/use-translations';
import { create, index, store } from '@/routes/competitions';

export default function CompetitionsCreate() {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('New competition')} />
            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">
                    {t('New competition')}
                </h1>
                <CompetitionForm
                    action={store().url}
                    method="post"
                    submitLabel={t('Create competition')}
                />
            </div>
        </>
    );
}

CompetitionsCreate.layout = {
    breadcrumbs: [
        { title: 'Competitions', href: index() },
        { title: 'New competition', href: create() },
    ],
};
