import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { CompetitionProps } from '@/components/competitions/competition-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { create, edit, index } from '@/routes/competitions';

type Props = {
    competitions: Array<CompetitionProps & { participants_count: number }>;
};

export default function CompetitionsIndex({ competitions }: Props) {
    const { t } = useTranslations();

    const statusLabels: Record<string, string> = {
        draft: t('Draft'),
        active: t('Active'),
        finished: t('Finished'),
    };

    return (
        <>
            <Head title={t('Competitions')} />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">
                        {t('Competitions')}
                    </h1>
                    <Button asChild>
                        <Link href={create()}>
                            <Plus />
                            {t('New competition')}
                        </Link>
                    </Button>
                </div>

                {competitions.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        {t('No competitions yet.')}
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left">
                                    <th className="p-3">{t('Name')}</th>
                                    <th className="p-3">{t('Status')}</th>
                                    <th className="p-3">{t('Start date')}</th>
                                    <th className="p-3">{t('Participants')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {competitions.map((competition) => (
                                    <tr
                                        key={competition.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3">
                                            <Link
                                                href={edit(competition.id)}
                                                className="font-medium hover:underline"
                                            >
                                                {competition.name}
                                            </Link>
                                        </td>
                                        <td className="p-3">
                                            <Badge variant="secondary">
                                                {
                                                    statusLabels[
                                                        competition.status
                                                    ]
                                                }
                                            </Badge>
                                        </td>
                                        <td className="p-3">
                                            {competition.starts_at}
                                        </td>
                                        <td className="p-3">
                                            {competition.participants_count}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

CompetitionsIndex.layout = {
    breadcrumbs: [{ title: 'Competitions', href: index() }],
};
