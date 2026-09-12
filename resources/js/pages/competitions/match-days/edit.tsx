import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { MatchDayProps } from '@/components/competitions/match-day-form';
import MatchDayForm from '@/components/competitions/match-day-form';
import type { MatchDayFieldProps } from '@/components/competitions/match-day-field-manager';
import MatchDayFieldManager from '@/components/competitions/match-day-field-manager';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useTranslations } from '@/hooks/use-translations';
import { formatDate } from '@/lib/format-date';
import { edit as editCompetition, index } from '@/routes/competitions';
import {
    destroy as destroyMatchDay,
    update as updateMatchDay,
} from '@/routes/competitions/match-days';

type Props = {
    competition: { id: number; name: string };
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
                        href={editCompetition(competition.id)}
                        className="text-muted-foreground flex items-center gap-1 text-sm hover:underline"
                    >
                        <ArrowLeft className="size-4" />
                        {t('Back to competition')}
                    </Link>
                    <h1 className="text-xl font-semibold">{title}</h1>
                    <p className="text-muted-foreground text-sm">
                        {competition.name}
                    </p>
                </div>

                <MatchDayForm
                    matchDay={matchDay}
                    action={updateMatchDay([competition.id, matchDay.id]).url}
                    method="put"
                    submitLabel={t('Save changes')}
                />

                <MatchDayFieldManager
                    competitionId={competition.id}
                    matchDayId={matchDay.id}
                    fields={fields}
                />

                <div className="max-w-xl border-t pt-6">
                    <Dialog>
                        <DialogTrigger asChild>
                            <Button variant="destructive">
                                {t('Delete match day')}
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>{t('Delete match day?')}</DialogTitle>
                            <DialogDescription>
                                {t(
                                    'This removes the match day and all its fields.',
                                )}
                            </DialogDescription>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        {t('Cancel')}
                                    </Button>
                                </DialogClose>
                                <Form
                                    action={
                                        destroyMatchDay([
                                            competition.id,
                                            matchDay.id,
                                        ]).url
                                    }
                                    method="delete"
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={processing}
                                        >
                                            {t('Delete match day')}
                                        </Button>
                                    )}
                                </Form>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        </>
    );
}

MatchDaysEdit.layout = {
    breadcrumbs: [{ title: 'Competitions', href: index() }],
};
