import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import MatchDayController from '@/actions/App/Http/Controllers/MatchDayController';
import type { MatchDayProps } from '@/components/competitions/match-day-form';
import MatchDayForm from '@/components/competitions/match-day-form';
import type { MatchDayFieldProps } from '@/components/competitions/match-day-field-manager';
import MatchDayFieldManager from '@/components/competitions/match-day-field-manager';
import ConfirmDialog from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { formatDate } from '@/lib/format-date';
import { edit as editCompetition, index } from '@/routes/competitions';
import {
    edit as editMatchDay,
    update as updateMatchDay,
} from '@/routes/competitions/match-days';

type Props = {
    competition: {
        id: number;
        name: string;
        starts_at: string;
        ends_at: string | null;
    };
    matchDay: MatchDayProps;
    fields: MatchDayFieldProps[];
};

export default function MatchDaysEdit({
    competition,
    matchDay,
    fields,
}: Props) {
    const { t } = useTranslations();
    const { locale } = usePage().props;
    const title = t('Match day :date', {
        date: formatDate(matchDay.date, locale),
    });

    return (
        <>
            <Head title={title} />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-col gap-1">
                    <Link
                        href={editCompetition(competition.id, {
                            query: { tab: 'match-days' },
                        })}
                        className="text-muted-foreground flex items-center gap-1 text-sm hover:underline"
                    >
                        <ArrowLeft className="size-4" />
                        {t('Back to competition')}
                    </Link>
                    <Heading
                        as="h1"
                        title={title}
                        description={competition.name}
                        className="mb-0"
                    />
                </div>

                <MatchDayForm
                    matchDay={matchDay}
                    action={updateMatchDay([competition.id, matchDay.id]).url}
                    method="put"
                    competitionStartsAt={competition.starts_at}
                    competitionEndsAt={competition.ends_at}
                    submitLabel={t('Save changes')}
                />

                <MatchDayFieldManager
                    competitionId={competition.id}
                    matchDayId={matchDay.id}
                    fields={fields}
                />

                <div className="max-w-xl border-t pt-6">
                    <ConfirmDialog
                        trigger={
                            <Button variant="destructive">
                                {t('Delete match day')}
                            </Button>
                        }
                        title={t('Delete match day?')}
                        description={t(
                            'This removes the match day and all its fields.',
                        )}
                        action={MatchDayController.destroy.form([
                            competition.id,
                            matchDay.id,
                        ])}
                        confirmLabel={t('Delete match day')}
                    />
                </div>
            </div>
        </>
    );
}

MatchDaysEdit.layout = ({
    competition,
    matchDay,
    locale,
}: Props & { locale: string }) => ({
    breadcrumbs: [
        { title: 'Competitions', href: index() },
        {
            title: competition.name,
            href: editCompetition(competition.id, {
                query: { tab: 'match-days' },
            }),
            translate: false,
        },
        {
            title: formatDate(matchDay.date, locale),
            href: editMatchDay([competition.id, matchDay.id]),
            translate: false,
        },
    ],
});
