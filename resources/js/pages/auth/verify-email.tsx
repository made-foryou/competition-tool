import { Form, Head, Link } from '@inertiajs/react';
import ConsoleButton from '@/components/console/console-button';
import ConsoleHeading from '@/components/console/console-heading';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('Verify your email address')} />

            <ConsoleHeading
                title={t('Verify your email address')}
                typewriter={t('> verification required')}
                className="mb-[18px]"
            />

            <p
                className="made-anim text-console-text/65 mb-6 text-sm leading-relaxed"
                style={{ '--made-delay': '0.82s' }}
            >
                {t(
                    "We've sent a verification link to your email address. Click the link to continue.",
                )}
            </p>

            {status === 'verification-link-sent' && (
                <p className="text-console-success made-anim mb-4 text-center text-sm">
                    {t('A new verification link has been sent.')}
                </p>
            )}

            <Form {...send.form()}>
                {({ processing }) => (
                    <div
                        className="made-anim"
                        style={{ '--made-delay': '0.94s' }}
                    >
                        <ConsoleButton
                            type="submit"
                            variant="secondary"
                            size="md"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            {processing
                                ? t('Sending…')
                                : t('Resend verification email')}
                        </ConsoleButton>
                    </div>
                )}
            </Form>

            <div
                className="made-anim mt-[22px] flex justify-center"
                style={{ '--made-delay': '1.06s' }}
            >
                <Link
                    href={logout()}
                    className="text-console-text/70 hover:text-console-text text-[13.5px] transition-colors"
                >
                    {t('Log out')}
                </Link>
            </div>
        </>
    );
}

VerifyEmail.layout = { label: 'verificatie' };
