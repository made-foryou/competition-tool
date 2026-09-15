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
    canSignUp: boolean;
};

export default function CompetitionLogin({
    competitionName,
    competitionSlug,
    status,
    canResetPassword,
    canSignUp,
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

            {/*
                Aanmelden kan alleen op een actieve competitie. De aanmeldlink
                verwees anders naar een inschrijfpagina die meteen weer
                terugstuurt naar deze loginpagina; in plaats daarvan krijgt de
                bezoeker de uitleg die daar ook staat. Een conceptcompetitie
                komt hier niet voor: die geeft uitgelogde bezoekers een 404.
            */}
            {canSignUp ? (
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
            ) : (
                <p
                    role="status"
                    className="made-anim text-console-text/65 mt-6 text-center text-sm"
                    style={{ '--made-delay': '1.6s' }}
                >
                    {t(
                        'Registration for this competition is closed. Contact the organizer if you have any questions.',
                    )}
                </p>
            )}
        </>
    );
}

CompetitionLogin.layout = { label: 'participant-login' };
