import { Form, Head, Link } from '@inertiajs/react';
import { AtSign, UserRound } from 'lucide-react';
import ConsoleButton from '@/components/console/console-button';
import ConsoleError from '@/components/console/console-error';
import ConsoleHeading from '@/components/console/console-heading';
import ConsoleInput from '@/components/console/console-input';
import ConsoleLabel from '@/components/console/console-label';
import ConsolePasswordInput from '@/components/console/console-password-input';
import type { MatchDayOption } from '@/components/match-day-checklist';
import MatchDayChecklist from '@/components/match-day-checklist';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { login } from '@/routes/competition';
import { store } from '@/routes/competition/register';

type Props = {
    competitionName: string;
    competitionSlug: string;
    authenticated: boolean;
    matchDays: MatchDayOption[];
    passwordRules: string;
};

export default function CompetitionRegister({
    competitionName,
    competitionSlug,
    authenticated,
    matchDays,
    passwordRules,
}: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head
                title={t('Sign up for :competition', {
                    competition: competitionName,
                })}
            />

            <ConsoleHeading
                title={competitionName}
                typewriter={t('> participant signup')}
                className="mb-[18px]"
            />

            <p
                className="made-anim text-console-text/65 mb-6 text-sm leading-relaxed"
                style={{ '--made-delay': '0.82s' }}
            >
                {authenticated
                    ? t('Let us know which match days you can attend.')
                    : t(
                          'Create your account and let us know which match days you can attend.',
                      )}
            </p>

            <Form {...store.form({ competition: competitionSlug })}>
                {({ processing, errors }) => (
                    <>
                        {!authenticated && (
                            <>
                                <div
                                    className="made-anim mb-4 flex flex-col gap-[7px]"
                                    style={{ '--made-delay': '0.94s' }}
                                >
                                    <ConsoleLabel htmlFor="name">
                                        {t('Name')}
                                    </ConsoleLabel>
                                    <ConsoleInput
                                        id="name"
                                        type="text"
                                        name="name"
                                        required
                                        autoFocus
                                        autoComplete="name"
                                        icon={
                                            <UserRound
                                                size={18}
                                                strokeWidth={1.6}
                                            />
                                        }
                                    />
                                    <ConsoleError message={errors.name} />
                                </div>

                                <div
                                    className="made-anim mb-4 flex flex-col gap-[7px]"
                                    style={{ '--made-delay': '1.06s' }}
                                >
                                    <ConsoleLabel htmlFor="nickname">
                                        {t('Nickname (optional)')}
                                    </ConsoleLabel>
                                    <ConsoleInput
                                        id="nickname"
                                        type="text"
                                        name="nickname"
                                        autoComplete="nickname"
                                        icon={
                                            <AtSign
                                                size={18}
                                                strokeWidth={1.6}
                                            />
                                        }
                                    />
                                    <ConsoleError message={errors.nickname} />
                                    <p className="text-console-text/65 text-xs">
                                        {t(
                                            'Other participants see your nickname instead of your name.',
                                        )}
                                    </p>
                                </div>

                                <div
                                    className="made-anim mb-4 flex flex-col gap-[7px]"
                                    style={{ '--made-delay': '1.18s' }}
                                >
                                    <ConsoleLabel htmlFor="email">
                                        {t('Email address')}
                                    </ConsoleLabel>
                                    <ConsoleInput
                                        id="email"
                                        type="email"
                                        name="email"
                                        required
                                        autoComplete="username"
                                    />
                                    <ConsoleError message={errors.email} />
                                </div>

                                <div
                                    className="made-anim mb-4 flex flex-col gap-[7px]"
                                    style={{ '--made-delay': '1.3s' }}
                                >
                                    <ConsoleLabel htmlFor="password">
                                        {t('Password')}
                                    </ConsoleLabel>
                                    <ConsolePasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        autoComplete="new-password"
                                        placeholder="••••••••"
                                        passwordrules={passwordRules}
                                    />
                                    <ConsoleError message={errors.password} />
                                </div>

                                <div
                                    className="made-anim mb-4 flex flex-col gap-[7px]"
                                    style={{ '--made-delay': '1.42s' }}
                                >
                                    <ConsoleLabel htmlFor="password_confirmation">
                                        {t('Confirm password')}
                                    </ConsoleLabel>
                                    <ConsolePasswordInput
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        required
                                        autoComplete="new-password"
                                        placeholder="••••••••"
                                    />
                                    <ConsoleError
                                        message={errors.password_confirmation}
                                    />
                                </div>
                            </>
                        )}

                        <div
                            className="made-anim mb-6 flex flex-col gap-[7px]"
                            style={{ '--made-delay': '1.54s' }}
                        >
                            <ConsoleLabel htmlFor="match_days">
                                {t('Which match days can you attend?')}
                            </ConsoleLabel>
                            <MatchDayChecklist
                                matchDays={matchDays}
                                variant="console"
                            />
                            <ConsoleError message={errors.match_days} />
                            {matchDays.length > 0 && (
                                <p className="text-console-text/65 text-xs">
                                    {t(
                                        'Leave everything unchecked if you cannot attend any match day.',
                                    )}
                                </p>
                            )}
                        </div>

                        <div
                            className="made-anim flex flex-col gap-4"
                            style={{ '--made-delay': '1.66s' }}
                        >
                            <ConsoleButton
                                type="submit"
                                variant="cta"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                {authenticated
                                    ? t('Join competition')
                                    : t('Sign up')}
                            </ConsoleButton>

                            {!authenticated && (
                                <p className="text-console-text/65 text-center text-sm">
                                    <Link
                                        href={login(competitionSlug)}
                                        className="hover:text-console-text underline"
                                    >
                                        {t('Already have an account? Log in')}
                                    </Link>
                                </p>
                            )}
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

CompetitionRegister.layout = { label: 'participant-signup' };
