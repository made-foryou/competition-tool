import { Head, Link } from '@inertiajs/react';
import ConsoleHeading from '@/components/console/console-heading';
import LoginForm from '@/components/console/login-form';
import { useTranslations } from '@/hooks/use-translations';
import { show as showRegister } from '@/routes/competition/register';

type Props = {
    competitionName: string;
    competitionSlug: string;
    status?: string;
    canResetPassword: boolean;
};

export default function CompetitionLogin({
    competitionName,
    competitionSlug,
    status,
    canResetPassword,
}: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={competitionName} />

            <ConsoleHeading
                title={competitionName}
                typewriter={t('> participant login')}
            />

            {status && (
                <p className="text-console-success made-anim mb-4 text-center text-sm">
                    {status}
                </p>
            )}

            <LoginForm canResetPassword={canResetPassword} />

            <p
                className="made-anim text-console-text/65 mt-6 text-center text-sm"
                style={{ '--made-delay': '1.6s' }}
            >
                <Link
                    href={showRegister(competitionSlug)}
                    className="hover:text-console-text underline"
                >
                    {t('No account yet? Sign up')}
                </Link>
            </p>
        </>
    );
}

CompetitionLogin.layout = { label: 'participant-login' };
