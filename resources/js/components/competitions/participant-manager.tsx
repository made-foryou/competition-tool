import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import {
    destroy as destroyParticipant,
    store as storeParticipant,
} from '@/routes/competitions/participants';

export type ParticipantProps = {
    id: number;
    name: string;
    nickname: string | null;
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
    participants: ParticipantProps[];
    pendingInvitations: PendingInvitationProps[];
};

export default function ParticipantManager({
    competitionId,
    participants,
    pendingInvitations,
}: Props) {
    const { t } = useTranslations();
    const [mode, setMode] = useState<'invite' | 'create'>('invite');

    return (
        <section className="flex max-w-xl flex-col gap-4">
            <h2 className="text-lg font-semibold">{t('Participants')}</h2>

            {participants.length === 0 ? (
                <p className="text-muted-foreground text-sm">
                    {t('No participants yet.')}
                </p>
            ) : (
                <ul className="divide-y rounded-xl border">
                    {participants.map((participant) => (
                        <li
                            key={participant.id}
                            className="flex items-center justify-between gap-2 p-3"
                        >
                            <div className="min-w-0">
                                <p className="truncate font-medium">
                                    {participant.nickname ?? participant.name}
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
                            <Form
                                action={
                                    destroyParticipant([
                                        competitionId,
                                        participant.id,
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
                                        date: invitation.expires_at,
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
                className="flex flex-col gap-4 rounded-xl border p-4"
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
                            />
                            <InputError message={errors.email} />
                            <p className="text-muted-foreground text-sm">
                                {t(
                                    'An existing account is linked directly. For a new email address, choose how the account is created.',
                                )}
                            </p>
                        </div>

                        <div className="flex gap-4">
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="radio"
                                    name="mode"
                                    value="invite"
                                    checked={mode === 'invite'}
                                    onChange={() => setMode('invite')}
                                />
                                {t('Send invitation email')}
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="radio"
                                    name="mode"
                                    value="create"
                                    checked={mode === 'create'}
                                    onChange={() => setMode('create')}
                                />
                                {t('Create account directly')}
                            </label>
                        </div>
                        <InputError message={errors.mode} />

                        {mode === 'create' && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="participant-name">
                                        {t('Name')}
                                    </Label>
                                    <Input id="participant-name" name="name" />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="participant-password">
                                        {t('Password')}
                                    </Label>
                                    <Input
                                        id="participant-password"
                                        name="password"
                                        type="password"
                                    />
                                    <InputError message={errors.password} />
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
