import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import {
    destroy as destroyField,
    store as storeField,
} from '@/routes/competitions/match-days/fields';

export type MatchDayFieldProps = {
    id: number;
    name: string;
    position: number;
};

type Props = {
    competitionId: number;
    matchDayId: number;
    fields: MatchDayFieldProps[];
};

export default function MatchDayFieldManager({
    competitionId,
    matchDayId,
    fields,
}: Props) {
    const { t } = useTranslations();

    return (
        <section className="flex max-w-xl flex-col gap-4">
            <h2 className="text-lg font-semibold">{t('Fields')}</h2>

            {fields.length === 0 ? (
                <p className="text-muted-foreground text-sm">
                    {t('No fields yet.')}
                </p>
            ) : (
                <ul className="divide-y rounded-xl border">
                    {fields.map((field) => (
                        <li
                            key={field.id}
                            className="flex items-center justify-between gap-2 p-3"
                        >
                            <span className="truncate font-medium">
                                {field.name}
                            </span>
                            <Form
                                action={
                                    destroyField([
                                        competitionId,
                                        matchDayId,
                                        field.id,
                                    ]).url
                                }
                                method="delete"
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="ghost"
                                        size="sm"
                                        disabled={processing}
                                    >
                                        {t('Remove')}
                                    </Button>
                                )}
                            </Form>
                        </li>
                    ))}
                </ul>
            )}

            <Form
                action={storeField([competitionId, matchDayId]).url}
                method="post"
                resetOnSuccess
                className="flex flex-col gap-4 rounded-xl border p-4"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="field-name">
                                {t('Field name')}
                            </Label>
                            <Input id="field-name" name="name" required />
                            <InputError message={errors.name} />
                        </div>

                        <div>
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                {t('Add field')}
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </section>
    );
}
