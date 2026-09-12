import { Form, usePage } from '@inertiajs/react';
import { Check, Copy, Users } from 'lucide-react';
import { useState } from 'react';
import CompetitionParticipantController from '@/actions/App/Http/Controllers/CompetitionParticipantController';
import ConfirmDialog from '@/components/confirm-dialog';
import EmptyState from '@/components/empty-state';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Spinner } from '@/components/ui/spinner';
import { useClipboard } from '@/hooks/use-clipboard';
import { useTranslations } from '@/hooks/use-translations';
import { formatDate } from '@/lib/format-date';
import competitionRoutes from '@/routes/competition';
import { store as storeParticipant } from '@/routes/competitions/participants';

export type ParticipantProps = {
    id: number;
    name: string;
    nickname: string | null;
    display_name: string;
    email: string;
    is_admin: boolean;
};

export type PendingInvitationProps = {
    id: number;
    email: string;
    expires_at: string;
};

type Props = {
    competitionId: number;
    competitionSlug: string;
    participants: ParticipantProps[];
    pendingInvitations: PendingInvitationProps[];
};

export default function ParticipantManager({
    competitionId,
    competitionSlug,
    participants,
    pendingInvitations,
}: Props) {
    const { t } = useTranslations();
    const { locale } = usePage().props;
    const [mode, setMode] = useState<'invite' | 'create'>('invite');

    const origin = typeof window !== 'undefined' ? window.location.origin : '';
    const registrationUrl = `${origin}${competitionRoutes.register.show(competitionSlug).url}`;
    const loginUrl = `${origin}${competitionRoutes.login(competitionSlug).url}`;

    return (
        <section className="flex flex-col gap-4">
            <div className="flex flex-col gap-2 rounded-xl border p-4">
                <h3 className="text-sm font-medium">
                    {t('Links for participants')}
                </h3>
                <CopyableLink
                    label={t('Registration link')}
                    url={registrationUrl}
                />
                <CopyableLink label={t('Login link')} url={loginUrl} />
            </div>

            {participants.length === 0 ? (
                <EmptyState
                    icon={Users}
                    title={t('No participants yet.')}
                    description={t('Add the first participant below.')}
                />
            ) : (
                <ul className="divide-y rounded-xl border">
                    {participants.map((participant) => {
                        const displayName = participant.display_name;

                        return (
                            <li
                                key={participant.id}
                                className="flex items-center justify-between gap-2 p-3"
                            >
                                <div className="min-w-0">
                                    <p className="truncate font-medium">
                                        {displayName}
                                        {participant.is_admin && (
                                            <Badge
                                                variant="secondary"
                                                className="ml-2"
                                            >
                                                {t('Admin')}
                                            </Badge>
                                        )}
                                    </p>
                                    <p className="text-muted-foreground truncate text-sm">
                                        {participant.nickname !== null &&
                                            `${participant.name} · `}
                                        {participant.email}
                                    </p>
                                </div>
                                <ConfirmDialog
                                    trigger={
                                        <Button
                                            variant="ghostDestructive"
                                            size="sm"
                                            aria-label={t('Remove :name', {
                                                name: displayName,
                                            })}
                                        >
                                            {t('Remove')}
                                        </Button>
                                    }
                                    title={t('Remove participant?')}
                                    description={t(
                                        'This removes :name from the competition. Their availability answers for this competition will no longer be shown.',
                                        { name: displayName },
                                    )}
                                    action={CompetitionParticipantController.destroy.form(
                                        [competitionId, participant.id],
                                    )}
                                    confirmLabel={t('Remove participant')}
                                />
                            </li>
                        );
                    })}
                </ul>
            )}

            {pendingInvitations.length > 0 && (
                <div className="flex flex-col gap-2">
                    <h3 className="text-sm font-medium">
                        {t('Pending invitations')}
                    </h3>
                    <ul className="divide-y rounded-xl border">
                        {pendingInvitations.map((invitation) => (
                            <li
                                key={invitation.id}
                                className="flex items-center justify-between p-3 text-sm"
                            >
                                <span>{invitation.email}</span>
                                <span className="text-muted-foreground">
                                    {t('Valid until :date', {
                                        date: formatDate(
                                            invitation.expires_at,
                                            locale,
                                        ),
                                    })}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <Form
                action={storeParticipant(competitionId).url}
                method="post"
                resetOnSuccess
                className="flex max-w-xl flex-col gap-4 rounded-xl border p-4"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="participant-email">
                                {t('Email address')}
                            </Label>
                            <Input
                                id="participant-email"
                                name="email"
                                type="email"
                                required
                                aria-invalid={!!errors.email}
                                aria-describedby={
                                    errors.email
                                        ? 'participant-email-error'
                                        : undefined
                                }
                            />
                            <InputError
                                id="participant-email-error"
                                message={errors.email}
                            />
                            <p className="text-muted-foreground text-sm">
                                {t(
                                    'An existing account is linked directly. For a new email address, choose how the account is created.',
                                )}
                            </p>
                        </div>

                        <input type="hidden" name="mode" value={mode} />
                        <fieldset className="grid gap-3">
                            <legend className="text-sm leading-none font-medium select-none">
                                {t('How does this participant get access?')}
                            </legend>
                            <RadioGroup
                                value={mode}
                                onValueChange={(value) =>
                                    setMode(value as 'invite' | 'create')
                                }
                                className="grid gap-3"
                                aria-invalid={!!errors.mode}
                                aria-describedby={
                                    errors.mode
                                        ? 'participant-mode-error'
                                        : undefined
                                }
                            >
                                <div className="flex items-start gap-2">
                                    <RadioGroupItem
                                        value="invite"
                                        id="participant-mode-invite"
                                        aria-invalid={!!errors.mode}
                                        className="mt-0.5"
                                    />
                                    <div className="grid gap-0.5">
                                        <Label
                                            htmlFor="participant-mode-invite"
                                            className="font-normal"
                                        >
                                            {t('Send invitation email')}
                                        </Label>
                                        <p className="text-muted-foreground text-sm">
                                            {t(
                                                'The participant sets their own password via the invitation email.',
                                            )}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-start gap-2">
                                    <RadioGroupItem
                                        value="create"
                                        id="participant-mode-create"
                                        aria-invalid={!!errors.mode}
                                        className="mt-0.5"
                                    />
                                    <div className="grid gap-0.5">
                                        <Label
                                            htmlFor="participant-mode-create"
                                            className="font-normal"
                                        >
                                            {t('Create account directly')}
                                        </Label>
                                        <p className="text-muted-foreground text-sm">
                                            {t(
                                                'You set the password and share it yourself.',
                                            )}
                                        </p>
                                    </div>
                                </div>
                            </RadioGroup>
                        </fieldset>
                        <InputError
                            id="participant-mode-error"
                            message={errors.mode}
                        />

                        {mode === 'create' && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="participant-name">
                                        {t('Name')}
                                    </Label>
                                    <Input
                                        id="participant-name"
                                        name="name"
                                        required
                                        aria-invalid={!!errors.name}
                                        aria-describedby={
                                            errors.name
                                                ? 'participant-name-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="participant-name-error"
                                        message={errors.name}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="participant-password">
                                        {t('Password')}
                                    </Label>
                                    <PasswordInput
                                        id="participant-password"
                                        name="password"
                                        required
                                        aria-invalid={!!errors.password}
                                        aria-describedby={
                                            errors.password
                                                ? 'participant-password-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="participant-password-error"
                                        message={errors.password}
                                    />
                                </div>
                            </div>
                        )}

                        <div>
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                {t('Add participant')}
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </section>
    );
}

function CopyableLink({ label, url }: { label: string; url: string }) {
    const { t } = useTranslations();
    const [copiedText, copy] = useClipboard();
    const isCopied = copiedText === url;

    return (
        <div className="grid gap-1.5">
            <span className="text-muted-foreground text-xs">{label}</span>
            <div className="flex items-center gap-2">
                <a
                    href={url}
                    target="_blank"
                    rel="noreferrer"
                    className="min-w-0 flex-1 truncate rounded-md border px-3 py-1.5 text-sm hover:underline"
                >
                    {url}
                </a>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    onClick={() => copy(url)}
                    aria-label={t('Copy link')}
                >
                    {isCopied ? (
                        <Check className="size-4" />
                    ) : (
                        <Copy className="size-4" />
                    )}
                </Button>
            </div>
        </div>
    );
}
