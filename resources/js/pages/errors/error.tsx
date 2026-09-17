import { Head, Link } from '@inertiajs/react';
import ConsoleHeading from '@/components/console/console-heading';
import { useTranslations } from '@/hooks/use-translations';

type Props = {
    status: number;
    returnUrl: string;
    returnLabel: string;
};

const labelByStatus: Record<number, string> = {
    403: 'access-denied',
    404: 'not-found',
    419: 'session-expired',
    500: 'server-error',
    503: 'maintenance',
};

export default function ErrorPage({ status, returnUrl, returnLabel }: Props) {
    const { t } = useTranslations();

    const copy = {
        403: {
            title: t('No access'),
            typewriter: t('> access denied'),
            description: t('You do not have access to this page.'),
        },
        404: {
            title: t('Page not found'),
            typewriter: t('> page not found'),
            description: t(
                'This page does not exist, or is no longer available.',
            ),
        },
        419: {
            title: t('Session expired'),
            typewriter: t('> session expired'),
            description: t(
                'Your session expired before the page was sent. Log in again and try once more.',
            ),
        },
        500: {
            title: t('Something went wrong'),
            typewriter: t('> server error'),
            description: t(
                'We could not process this request. Try again later.',
            ),
        },
        503: {
            title: t('Temporarily unavailable'),
            typewriter: t('> maintenance'),
            description: t('We are doing maintenance. Check back in a moment.'),
        },
        // Een status die hier niet in staat hoort niet te bestaan -- de
        // handler filtert erop -- maar een lege pagina is een slechtere
        // uitkomst dan de algemene serverfout.
    }[status] ?? {
        title: t('Something went wrong'),
        typewriter: t('> server error'),
        description: t('We could not process this request. Try again later.'),
    };

    return (
        <>
            <Head title={copy.title} />

            <ConsoleHeading
                title={copy.title}
                typewriter={copy.typewriter}
                className="mb-[18px]"
            />

            <p
                role="status"
                className="made-anim text-console-text/65 mb-6 text-sm leading-relaxed"
                style={{ '--made-delay': '0.82s' }}
            >
                {copy.description}
            </p>

            <p
                className="made-anim text-console-text/65 text-sm"
                style={{ '--made-delay': '0.94s' }}
            >
                <Link
                    href={returnUrl}
                    className="hover:text-console-text underline"
                >
                    {returnLabel}
                </Link>
            </p>
        </>
    );
}

ErrorPage.layout = ({ status }: Props) => ({
    label: labelByStatus[status] ?? 'error',
});
