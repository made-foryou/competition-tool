import { Form, Head, Link } from '@inertiajs/react';
import { ArrowRight, LockKeyhole, Mail } from 'lucide-react';
import ConsoleButton from '@/components/console/console-button';
import ConsoleError from '@/components/console/console-error';
import ConsoleHeading from '@/components/console/console-heading';
import ConsoleInput from '@/components/console/console-input';
import ConsoleLabel from '@/components/console/console-label';
import ConsolePasskeyButton from '@/components/console/console-passkey-button';
import ConsolePasswordInput from '@/components/console/console-password-input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('Log in')} />

            <ConsoleHeading
                title={t('Access the console')}
                typewriter={t('> authentication required')}
            />

            {status && (
                <p className="text-console-success made-anim mb-4 text-center text-sm">
                    {status}
                </p>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col"
            >
                {({ processing, errors }) => (
                    <>
                        <div
                            className="made-anim mb-4 flex flex-col gap-[7px]"
                            style={{ '--made-delay': '0.82s' }}
                        >
                            <ConsoleLabel htmlFor="email">
                                {t('Email address')}
                            </ConsoleLabel>
                            <ConsoleInput
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="email"
                                placeholder={t('you@made.nl')}
                                icon={<Mail size={18} strokeWidth={1.6} />}
                            />
                            <ConsoleError message={errors.email} />
                        </div>

                        <div
                            className="made-anim mb-3.5 flex flex-col gap-[7px]"
                            style={{ '--made-delay': '0.94s' }}
                        >
                            <ConsoleLabel htmlFor="password">
                                {t('Password')}
                            </ConsoleLabel>
                            <ConsolePasswordInput
                                id="password"
                                name="password"
                                required
                                tabIndex={2}
                                autoComplete="current-password"
                                placeholder="••••••••"
                            />
                            <ConsoleError message={errors.password} />
                        </div>

                        <div
                            className="made-anim mb-[22px] flex items-center justify-between"
                            style={{ '--made-delay': '1.06s' }}
                        >
                            <label className="text-console-text/70 inline-flex cursor-pointer items-center gap-2 text-[13.5px]">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    tabIndex={3}
                                    className="accent-copper size-[15px]"
                                />
                                {t('Remember me')}
                            </label>
                            {canResetPassword && (
                                <Link
                                    href={request()}
                                    className="text-copper text-[13.5px] hover:underline"
                                    tabIndex={5}
                                >
                                    {t('Forgot your password?')}
                                </Link>
                            )}
                        </div>

                        <div
                            className="made-anim"
                            style={{ '--made-delay': '1.18s' }}
                        >
                            <ConsoleButton
                                type="submit"
                                variant="cta"
                                size="lg"
                                className="w-full"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                {processing ? t('Logging in…') : t('Log in')}
                                {!processing && (
                                    <ArrowRight size={17} strokeWidth={1.8} />
                                )}
                            </ConsoleButton>
                        </div>
                    </>
                )}
            </Form>

            <div className="made-anim mt-5" style={{ '--made-delay': '1.24s' }}>
                <div className="mb-5 flex items-center gap-3">
                    <span className="bg-console-border h-px flex-1" />
                    <span className="text-console-text/40 font-mono text-[11px] uppercase">
                        {t('or')}
                    </span>
                    <span className="bg-console-border h-px flex-1" />
                </div>
                <ConsolePasskeyButton
                    label={t('Log in with passkey')}
                    loadingLabel={t('Logging in…')}
                />
            </div>

            <p
                className="made-anim text-console-text/40 mt-[22px] mb-0 flex items-center justify-center gap-[7px] font-mono text-[11px] tracking-[0.04em]"
                style={{ '--made-delay': '1.3s' }}
            >
                <LockKeyhole size={13} strokeWidth={1.8} />
                {t('Secured with 2FA · end-to-end encrypted')}
            </p>
        </>
    );
}

Login.layout = { label: 'secure-login' };
