import { Form, Head } from '@inertiajs/react';
import { ArrowRight, Mail } from 'lucide-react';
import ConsoleButton from '@/components/console/console-button';
import ConsoleError from '@/components/console/console-error';
import ConsoleHeading from '@/components/console/console-heading';
import ConsoleInput from '@/components/console/console-input';
import ConsoleLabel from '@/components/console/console-label';
import ConsolePasswordInput from '@/components/console/console-password-input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { update } from '@/routes/password';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
};

export default function ResetPassword({ token, email, passwordRules }: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('Set a new password')} />

            <ConsoleHeading
                title={t('Set a new password')}
                typewriter={t('> restore access')}
                className="mb-[18px]"
            />

            <p
                className="made-anim text-console-text/65 mb-6 text-sm leading-relaxed"
                style={{ '--made-delay': '0.82s' }}
            >
                {t('Choose a strong, new password for your account.')}
            </p>

            <Form
                {...update.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
            >
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
                                name="email"
                                autoComplete="email"
                                value={email}
                                readOnly
                                className="opacity-70"
                                icon={<Mail size={18} strokeWidth={1.6} />}
                            />
                            <ConsoleError message={errors.email} />
                        </div>

                        <div
                            className="made-anim mb-4 flex flex-col gap-[7px]"
                            style={{ '--made-delay': '1.06s' }}
                        >
                            <ConsoleLabel htmlFor="password">
                                {t('New password')}
                            </ConsoleLabel>
                            <ConsolePasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                autoFocus
                                required
                                placeholder="••••••••"
                                passwordrules={passwordRules}
                            />
                            <ConsoleError message={errors.password} />
                        </div>

                        <div
                            className="made-anim mb-[22px] flex flex-col gap-[7px]"
                            style={{ '--made-delay': '1.18s' }}
                        >
                            <ConsoleLabel htmlFor="password_confirmation">
                                {t('Confirm password')}
                            </ConsoleLabel>
                            <ConsolePasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                required
                                placeholder="••••••••"
                                passwordrules={passwordRules}
                            />
                            <ConsoleError
                                message={errors.password_confirmation}
                            />
                        </div>

                        <div
                            className="made-anim"
                            style={{ '--made-delay': '1.3s' }}
                        >
                            <ConsoleButton
                                type="submit"
                                variant="cta"
                                size="lg"
                                className="w-full"
                                disabled={processing}
                                data-test="reset-password-button"
                            >
                                {processing && <Spinner />}
                                {processing ? t('Saving…') : t('Save password')}
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

ResetPassword.layout = { label: 'herstel' };
