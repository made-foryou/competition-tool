import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';

export type CompetitionProps = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    location: string | null;
    starts_at: string;
    ends_at: string | null;
    status: string;
};

type Props = {
    competition?: CompetitionProps;
    action: string;
    method: 'post' | 'put';
    submitLabel: string;
};

const STATUSES = ['draft', 'active', 'finished'] as const;

export default function CompetitionForm({
    competition,
    action,
    method,
    submitLabel,
}: Props) {
    const { t } = useTranslations();

    const statusLabels: Record<string, string> = {
        draft: t('Draft'),
        active: t('Active'),
        finished: t('Finished'),
    };

    return (
        <Form
            action={action}
            method={method}
            className="flex max-w-xl flex-col gap-6"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="name">{t('Name')}</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            defaultValue={competition?.name ?? ''}
                        />
                        <InputError message={errors.name} />
                        <InputError message={errors.slug} />
                        {competition && (
                            <p className="text-muted-foreground text-sm">
                                {t('URL: :url', {
                                    url: `/${competition.slug}`,
                                })}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">{t('Description')}</Label>
                        <textarea
                            id="description"
                            name="description"
                            rows={4}
                            defaultValue={competition?.description ?? ''}
                            className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-[3px]"
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="location">{t('Location')}</Label>
                        <Input
                            id="location"
                            name="location"
                            defaultValue={competition?.location ?? ''}
                        />
                        <InputError message={errors.location} />
                    </div>

                    <div className="grid gap-6 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="starts_at">{t('Start date')}</Label>
                            <Input
                                id="starts_at"
                                name="starts_at"
                                type="date"
                                required
                                defaultValue={competition?.starts_at ?? ''}
                            />
                            <InputError message={errors.starts_at} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ends_at">{t('End date')}</Label>
                            <Input
                                id="ends_at"
                                name="ends_at"
                                type="date"
                                defaultValue={competition?.ends_at ?? ''}
                            />
                            <InputError message={errors.ends_at} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="status">{t('Status')}</Label>
                        <Select
                            name="status"
                            defaultValue={competition?.status ?? 'draft'}
                        >
                            <SelectTrigger id="status" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUSES.map((status) => (
                                    <SelectItem key={status} value={status}>
                                        {statusLabels[status]}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.status} />
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
