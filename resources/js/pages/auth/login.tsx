import { Head } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';
import ConsoleHeading from '@/components/console/console-heading';
import LoginForm from '@/components/console/login-form';
import { useTranslations } from '@/hooks/use-translations';

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

            <LoginForm canResetPassword={canResetPassword} />

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
