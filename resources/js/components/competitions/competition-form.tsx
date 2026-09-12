import { Form } from '@inertiajs/react';
import { DatePicker } from '@/components/date-picker';
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
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/hooks/use-translations';
import {
    COMPETITION_STATUSES,
    competitionStatusLabel,
} from '@/lib/competition-status';

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

export default function CompetitionForm({
    competition,
    action,
    method,
    submitLabel,
}: Props) {
    const { t } = useTranslations();

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
                            aria-invalid={!!errors.name || !!errors.slug}
                            aria-describedby={
                                errors.name || errors.slug
                                    ? 'name-error'
                                    : undefined
                            }
                        />
                        <InputError
                            id="name-error"
                            message={errors.name ?? errors.slug}
                        />
                        {competition && (
                            <p className="text-muted-foreground text-sm">
                                {t('URL: :url', {
                                    url: `/${competition.slug}`,
                                })}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">
                            {t('Description (optional)')}
                        </Label>
                        <Textarea
                            id="description"
                            name="description"
                            rows={4}
                            defaultValue={competition?.description ?? ''}
                            aria-invalid={!!errors.description}
                            aria-describedby={
                                errors.description
                                    ? 'description-error'
                                    : undefined
                            }
                        />
                        <InputError
                            id="description-error"
                            message={errors.description}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="location">
                            {t('Location (optional)')}
                        </Label>
                        <Input
                            id="location"
                            name="location"
                            defaultValue={competition?.location ?? ''}
                            aria-invalid={!!errors.location}
                            aria-describedby={
                                errors.location ? 'location-error' : undefined
                            }
                        />
                        <InputError
                            id="location-error"
                            message={errors.location}
                        />
                    </div>

                    <div className="grid gap-6 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="starts_at">{t('Start date')}</Label>
                            <DatePicker
                                id="starts_at"
                                name="starts_at"
                                required
                                defaultValue={competition?.starts_at}
                                aria-invalid={!!errors.starts_at}
                                aria-describedby={
                                    errors.starts_at
                                        ? 'starts_at-error'
                                        : undefined
                                }
                            />
                            <InputError
                                id="starts_at-error"
                                message={errors.starts_at}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ends_at">
                                {t('End date (optional)')}
                            </Label>
                            <DatePicker
                                id="ends_at"
                                name="ends_at"
                                clearable
                                defaultValue={competition?.ends_at}
                                aria-invalid={!!errors.ends_at}
                                aria-describedby={
                                    errors.ends_at ? 'ends_at-error' : undefined
                                }
                            />
                            <InputError
                                id="ends_at-error"
                                message={errors.ends_at}
                            />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="status">{t('Status')}</Label>
                        <Select
                            name="status"
                            defaultValue={competition?.status ?? 'draft'}
                        >
                            <SelectTrigger
                                id="status"
                                className="w-full"
                                aria-invalid={!!errors.status}
                                aria-describedby={
                                    errors.status ? 'status-error' : undefined
                                }
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {COMPETITION_STATUSES.map((status) => (
                                    <SelectItem key={status} value={status}>
                                        {competitionStatusLabel(status, t)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError id="status-error" message={errors.status} />
                        <p className="text-muted-foreground text-sm">
                            {t(
                                'Draft competitions are only visible to administrators. Participants can only sign in when the competition is active.',
                            )}
                        </p>
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
