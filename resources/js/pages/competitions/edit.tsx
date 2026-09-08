import { Form, Head } from '@inertiajs/react';
import type { CompetitionProps } from '@/components/competitions/competition-form';
import CompetitionForm from '@/components/competitions/competition-form';
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
import { destroy, index, update } from '@/routes/competitions';

type Props = {
    competition: CompetitionProps;
};

export default function CompetitionsEdit({ competition }: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={competition.name} />
            <div className="flex flex-col gap-6 p-4">
                <h1 className="text-xl font-semibold">{competition.name}</h1>

                <CompetitionForm
                    competition={competition}
                    action={update(competition.id).url}
                    method="put"
                    submitLabel={t('Save changes')}
                />

                <div className="max-w-xl border-t pt-6">
                    <Dialog>
                        <DialogTrigger asChild>
                            <Button variant="destructive">
                                {t('Delete competition')}
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>
                                {t('Delete competition?')}
                            </DialogTitle>
                            <DialogDescription>
                                {t(
                                    'This removes the competition and its participant list. User accounts are kept.',
                                )}
                            </DialogDescription>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        {t('Cancel')}
                                    </Button>
                                </DialogClose>
                                <Form
                                    action={destroy(competition.id).url}
                                    method="delete"
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={processing}
                                        >
                                            {t('Delete competition')}
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

CompetitionsEdit.layout = {
    breadcrumbs: [{ title: 'Competitions', href: index() }],
};
