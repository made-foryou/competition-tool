import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Mail, UserRound } from 'lucide-react';
import ConsoleButton from '@/components/console/console-button';
import ConsoleError from '@/components/console/console-error';
import ConsoleHeading from '@/components/console/console-heading';
import ConsoleInput from '@/components/console/console-input';
import ConsoleLabel from '@/components/console/console-label';
import ConsolePasswordInput from '@/components/console/console-password-input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { login } from '@/routes';
import { store } from '@/routes/invitation';

type Props = {
    expired: boolean;
    email?: string;
    token?: string;
    passwordRules?: string;
};

export default function AcceptInvitation({
    expired,
    email,
    token = '',
    passwordRules,
}: Props) {
    const { t } = useTranslations();

    if (expired) {
        return (
            <>
                <Head title={t('This invitation is no longer valid')} />

                <ConsoleHeading
                    title={t('This invitation is no longer valid')}
                    typewriter={t('> account setup')}
                    className="mb-[18px]"
                />

                <p
                    className="made-anim text-console-text/65 mb-6 text-sm leading-relaxed"
                    style={{ '--made-delay': '0.82s' }}
                >
                    {t(
                        'The invitation has expired or has already been used. Ask an administrator to send you a new invitation.',
                    )}
                </p>

                <div
                    className="made-anim flex justify-center"
                    style={{ '--made-delay': '0.94s' }}
                >
                    <Link
                        href={login()}
                        className="text-console-text/70 hover:text-console-text inline-flex items-center gap-[7px] text-[13.5px] transition-colors"
                    >
                        <ArrowLeft size={15} strokeWidth={1.8} />
                        {t('Back to login')}
                    </Link>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={t("You're invited")} />

            <ConsoleHeading
                title={t("You're invited")}
                typewriter={t('> account setup')}
                className="mb-[18px]"
            />

            <p
                className="made-anim text-console-text/65 mb-6 text-sm leading-relaxed"
                style={{ '--made-delay': '0.82s' }}
            >
                {t('Set your name and a password to activate your account for')}{' '}
                <span className="text-console-text font-mono text-[13px]">
                    {email}
                </span>
            </p>

            <Form {...store.form({ token })}>
                {({ processing, errors }) => (
                    <>
                        <div
                            className="made-anim mb-4 flex flex-col gap-[7px]"
                            style={{ '--made-delay': '0.94s' }}
                        >
                            <ConsoleLabel htmlFor="email">
                                {t('Email address')}
                            </ConsoleLabel>
                            <ConsoleInput
                                id="email"
                                type="email"
                                value={email}
                                readOnly
                                className="opacity-70"
                                icon={<Mail size={18} strokeWidth={1.6} />}
                            />
                        </div>

                        <div
                            className="made-anim mb-4 flex flex-col gap-[7px]"
                            style={{ '--made-delay': '1.06s' }}
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
                                icon={<UserRound size={18} strokeWidth={1.6} />}
                            />
                            <ConsoleError message={errors.name} />
                        </div>

                        <div
                            className="made-anim mb-4 flex flex-col gap-[7px]"
                            style={{ '--made-delay': '1.18s' }}
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
                            className="made-anim mb-[22px] flex flex-col gap-[7px]"
                            style={{ '--made-delay': '1.3s' }}
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
                                passwordrules={passwordRules}
                            />
                            <ConsoleError
                                message={errors.password_confirmation}
                            />
                        </div>

                        <div
                            className="made-anim"
                            style={{ '--made-delay': '1.42s' }}
                        >
                            <ConsoleButton
                                type="submit"
                                variant="cta"
                                size="lg"
                                className="w-full"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                {processing
                                    ? t('Activating…')
                                    : t('Activate account')}
                                {!processing && (
                                    <ArrowRight size={17} strokeWidth={1.8} />
                                )}
                            </ConsoleButton>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

AcceptInvitation.layout = { label: 'uitnodiging' };
