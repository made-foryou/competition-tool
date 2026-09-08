import { Head } from '@inertiajs/react';
import ConsoleHeading from '@/components/console/console-heading';
import LoginForm from '@/components/console/login-form';
import { useTranslations } from '@/hooks/use-translations';

type Props = {
    competitionName: string;
    status?: string;
    canResetPassword: boolean;
};

export default function CompetitionLogin({
    competitionName,
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
        </>
    );
}

CompetitionLogin.layout = { label: 'participant-login' };
