import { Form, usePage } from '@inertiajs/react';
import { Check, Copy, Search, Users, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import CompetitionInvitationController from '@/actions/App/Http/Controllers/CompetitionInvitationController';
import CompetitionParticipantController from '@/actions/App/Http/Controllers/CompetitionParticipantController';
import CompetitionParticipantRoleController from '@/actions/App/Http/Controllers/CompetitionParticipantRoleController';
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
import { pluralize } from '@/lib/plural';
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

/**
 * Aantal deelnemers dat we in één keer tonen. "Toon meer" verhoogt met
 * dezelfde stap, zodat een competitie met honderden deelnemers niet in één
 * keer de hele pagina volzet.
 */
const PAGE_SIZE = 25;

export default function ParticipantManager({
    competitionId,
    competitionSlug,
    participants,
    pendingInvitations,
}: Props) {
    const { t } = useTranslations();
    const { auth, locale } = usePage().props;
    const [mode, setMode] = useState<'invite' | 'create'>('invite');
    const [search, setSearch] = useState('');
    const [visible, setVisible] = useState(PAGE_SIZE);

    /** De volledige deelnemerslijst zit in de props, dus filteren kan in de browser. */
    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();

        if (term === '') {
            return participants;
        }

        return participants.filter((participant) =>
            [participant.name, participant.nickname, participant.email].some(
                (value) => value?.toLowerCase().includes(term),
            ),
        );
    }, [participants, search]);

    /** Een nieuwe zoekterm hoort weer bij de eerste pagina te beginnen. */
    function handleSearchChange(value: string) {
        setSearch(value);
        setVisible(PAGE_SIZE);
    }

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
                <div className="flex flex-col gap-3">
                    <div className="relative sm:max-w-xs">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) =>
                                handleSearchChange(event.target.value)
                            }
                            placeholder={t('Search by name or email…')}
                            aria-label={t('Search participants')}
                            maxLength={100}
                            className="pl-9"
                        />
                    </div>

                    {filtered.length === 0 ? (
                        <EmptyState
                            icon={Search}
                            title={t('No participants match this search.')}
                            action={
                                <Button
                                    variant="outline"
                                    onClick={() => handleSearchChange('')}
                                >
                                    <X />
                                    {t('Clear search')}
                                </Button>
                            }
                        />
                    ) : (
                        <>
                            <p className="text-muted-foreground text-sm">
                                {pluralize(
                                    t,
                                    filtered.length,
                                    ':count participant',
                                    ':count participants',
                                )}
                            </p>

                            <ul className="divide-y rounded-xl border">
                                {filtered
                                    .slice(0, visible)
                                    .map((participant) => (
                                        <ParticipantRow
                                            key={participant.id}
                                            competitionId={competitionId}
                                            participant={participant}
                                            isCurrentUser={
                                                participant.id === auth.user?.id
                                            }
                                        />
                                    ))}
                            </ul>

                            {filtered.length > visible && (
                                <div>
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            setVisible(
                                                (current) =>
                                                    current + PAGE_SIZE,
                                            )
                                        }
                                    >
                                        {t('Show more (:shown of :total)', {
                                            shown: visible,
                                            total: filtered.length,
                                        })}
                                    </Button>
                                </div>
                            )}
                        </>
                    )}
                </div>
            )}

            {pendingInvitations.length > 0 && (
                <div className="flex flex-col gap-2">
                    <h3 className="text-sm font-medium">
                        {t('Pending invitations')}
                    </h3>
                    <p className="text-muted-foreground text-sm">
                        {t(
                            'Resending creates a new link. The previous link stops working.',
                        )}
                    </p>
                    <ul className="divide-y rounded-xl border">
                        {pendingInvitations.map((invitation) => (
                            <li
                                key={invitation.email}
                                className="flex flex-col gap-2 p-3 text-sm sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                            >
                                <div className="min-w-0">
                                    <p className="truncate">
                                        {invitation.email}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {t('Valid until :date', {
                                            date: formatDate(
                                                invitation.expires_at,
                                                locale,
                                            ),
                                        })}
                                    </p>
                                </div>
                                <div className="flex shrink-0 items-center gap-2">
                                    <Form
                                        {...CompetitionInvitationController.resend.form(
                                            [competitionId, invitation.id],
                                        )}
                                        options={{ preserveScroll: true }}
                                        onError={() =>
                                            toast.error(
                                                t('Something went wrong.'),
                                            )
                                        }
                                    >
                                        {({ processing }) => (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                type="submit"
                                                disabled={processing}
                                                aria-label={t(
                                                    'Resend invitation to :email',
                                                    { email: invitation.email },
                                                )}
                                            >
                                                {processing && <Spinner />}
                                                {t('Resend')}
                                            </Button>
                                        )}
                                    </Form>
                                    <ConfirmDialog
                                        trigger={
                                            <Button
                                                variant="ghostDestructive"
                                                size="sm"
                                                aria-label={t(
                                                    'Withdraw invitation for :email',
                                                    { email: invitation.email },
                                                )}
                                            >
                                                {t('Withdraw')}
                                            </Button>
                                        }
                                        title={t('Withdraw invitation?')}
                                        description={t(
                                            'This withdraws the invitation for :email. The invitation link stops working.',
                                            { email: invitation.email },
                                        )}
                                        action={CompetitionInvitationController.destroy.form(
                                            [competitionId, invitation.id],
                                        )}
                                        confirmLabel={t('Withdraw invitation')}
                                    />
                                </div>
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

/**
 * Eén regel uit de deelnemerslijst: naam, rolbadge en de acties.
 *
 * De rolactie ontbreekt op de eigen regel: de backend weigert zelfwijziging
 * met een 403, omdat wie zichzelf degradeert zich meteen buiten de console
 * sluit.
 */
function ParticipantRow({
    competitionId,
    participant,
    isCurrentUser,
}: {
    competitionId: number;
    participant: ParticipantProps;
    isCurrentUser: boolean;
}) {
    const { t } = useTranslations();
    const displayName = participant.display_name;
    const isAdmin = participant.is_admin;

    return (
        <li className="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
            <div className="min-w-0">
                <p className="truncate font-medium">
                    {displayName}
                    {isAdmin && (
                        <Badge variant="secondary" className="ml-2">
                            {t('Admin')}
                        </Badge>
                    )}
                </p>
                <p className="text-muted-foreground truncate text-sm">
                    {participant.nickname !== null && `${participant.name} · `}
                    {participant.email}
                </p>
            </div>
            <div className="flex shrink-0 items-center gap-2">
                {!isCurrentUser && (
                    <ConfirmDialog
                        trigger={
                            <Button
                                variant="outline"
                                size="sm"
                                aria-label={
                                    isAdmin
                                        ? t(
                                              'Remove administrator rights from :name',
                                              { name: displayName },
                                          )
                                        : t('Make :name an administrator', {
                                              name: displayName,
                                          })
                                }
                            >
                                {isAdmin
                                    ? t('Remove admin rights')
                                    : t('Make administrator')}
                            </Button>
                        }
                        title={
                            isAdmin
                                ? t('Remove administrator rights?')
                                : t('Make administrator?')
                        }
                        description={
                            isAdmin
                                ? t(
                                      ':name loses access to the management of all competitions. :name stays a participant of this competition.',
                                      { name: displayName },
                                  )
                                : t(
                                      ':name gets access to the management of all competitions, not just this one. On their next sign-in they must set up a second factor (authenticator app or passkey). They no longer have to fill in their availability, because that only applies to participants.',
                                      { name: displayName },
                                  )
                        }
                        action={CompetitionParticipantRoleController.form([
                            competitionId,
                            participant.id,
                        ])}
                        fields={
                            <input
                                type="hidden"
                                name="role"
                                value={isAdmin ? 'participant' : 'admin'}
                            />
                        }
                        confirmLabel={
                            isAdmin
                                ? t('Remove admin rights')
                                : t('Make administrator')
                        }
                        confirmVariant={isAdmin ? 'destructive' : 'default'}
                    />
                )}
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
                        'This removes :name from the competition. Their availability answers and unplayed matches are removed; played matches are kept.',
                        { name: displayName },
                    )}
                    action={CompetitionParticipantController.destroy.form([
                        competitionId,
                        participant.id,
                    ])}
                    confirmLabel={t('Remove participant')}
                />
            </div>
        </li>
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
