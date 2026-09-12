import { Head } from '@inertiajs/react';
import CompetitionForm from '@/components/competitions/competition-form';
import Heading from '@/components/heading';
import { useTranslations } from '@/hooks/use-translations';
import { create, index, store } from '@/routes/competitions';

export default function CompetitionsCreate() {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('New competition')} />
            <div className="flex flex-col gap-4 p-4">
                <Heading title={t('New competition')} className="mb-0" />
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
