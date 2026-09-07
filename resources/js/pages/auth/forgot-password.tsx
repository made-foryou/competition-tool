import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Mail } from 'lucide-react';
import { useState } from 'react';
import ConsoleButton from '@/components/console/console-button';
import ConsoleError from '@/components/console/console-error';
import ConsoleHeading from '@/components/console/console-heading';
import ConsoleInput from '@/components/console/console-input';
import ConsoleLabel from '@/components/console/console-label';
import SuccessCheck from '@/components/console/success-check';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { login } from '@/routes';
import { email as emailRoute } from '@/routes/password';

export default function ForgotPassword() {
    const { t } = useTranslations();
    const [email, setEmail] = useState('');
    const [sent, setSent] = useState(false);

    const backToLogin = (
        <div className="mt-[22px] flex justify-center">
            <Link
                href={login()}
                className="text-console-text/70 hover:text-console-text inline-flex items-center gap-[7px] text-[13.5px] transition-colors"
            >
                <ArrowLeft size={15} strokeWidth={1.8} />
                {t('Back to login')}
            </Link>
        </div>
    );

    return (
        <>
            <Head title={t('Forgot your password?')} />

            <Form {...emailRoute.form()} onSuccess={() => setSent(true)}>
                {({ processing, errors }) =>
                    sent ? (
                        <div className="made-pop text-center">
                            <SuccessCheck />
                            <h1 className="text-console-text mb-2.5 font-serif text-[25px] font-semibold tracking-[-0.01em]">
                                {t('Check your inbox')}
                            </h1>
                            <p className="text-console-text/65 mb-1.5 text-sm leading-relaxed">
                                {t("We've sent a recovery link to")}
                            </p>
                            <p className="text-console-text mb-2 font-mono text-[13.5px]">
                                {email}
                            </p>
                            <p className="text-console-text/45 mb-[26px] text-[12.5px] leading-normal">
                                {t(
                                    'The link is valid for 30 minutes. Nothing received? Check your spam folder.',
                                )}
                            </p>

                            <input type="hidden" name="email" value={email} />
                            <ConsoleButton
                                type="submit"
                                variant="secondary"
                                size="md"
                                className="w-full"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                {processing ? t('Sending…') : t('Resend')}
                            </ConsoleButton>

                            {backToLogin}
                        </div>
                    ) : (
                        <>
                            <ConsoleHeading
                                title={t('Forgot your password?')}
                                typewriter={t('> restore access')}
                                className="mb-[18px]"
                            />

                            <p
                                className="made-anim text-console-text/65 mb-6 text-sm leading-relaxed"
                                style={{ '--made-delay': '0.82s' }}
                            >
                                {t(
                                    "No problem. Enter your email address and we'll send you a link to set a new password.",
                                )}
                            </p>

                            <div
                                className="made-anim mb-[22px] flex flex-col gap-[7px]"
                                style={{ '--made-delay': '0.94s' }}
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
                                    autoComplete="email"
                                    placeholder={t('you@made.nl')}
                                    value={email}
                                    onChange={(event) =>
                                        setEmail(event.target.value)
                                    }
                                    icon={<Mail size={18} strokeWidth={1.6} />}
                                />
                                <ConsoleError message={errors.email} />
                            </div>

                            <div
                                className="made-anim"
                                style={{ '--made-delay': '1.06s' }}
                            >
                                <ConsoleButton
                                    type="submit"
                                    variant="cta"
                                    size="lg"
                                    className="w-full"
                                    disabled={processing}
                                    data-test="email-password-reset-link-button"
                                >
                                    {processing && <Spinner />}
                                    {processing
                                        ? t('Sending…')
                                        : t('Send recovery link')}
                                    {!processing && (
                                        <ArrowRight
                                            size={17}
                                            strokeWidth={1.8}
                                        />
                                    )}
                                </ConsoleButton>
                            </div>

                            <div
                                className="made-anim"
                                style={{ '--made-delay': '1.18s' }}
                            >
                                {backToLogin}
                            </div>
                        </>
                    )
                }
            </Form>
        </>
    );
}

ForgotPassword.layout = { label: 'herstel' };
