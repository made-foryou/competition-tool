import { Form, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, LogOut, Settings } from 'lucide-react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useTranslations } from '@/hooks/use-translations';
import { logout } from '@/routes';
import { dashboard } from '@/routes/competition';
import { index as settingsIndex } from '@/routes/competition/settings';

type ParticipantPageProps = {
    competition?: { name: string; slug: string };
};

/**
 * Shared shell for the participant-facing pages under `participant/*`:
 * a slim header with the app logo, the active competition name and a
 * logout button, and a max-width, mobile-first content area. Inside the
 * settings section the settings button turns into a back button.
 */
export default function ParticipantLayout({
    children,
}: {
    children: ReactNode;
}) {
    const { t } = useTranslations();
    const { competition } = usePage<ParticipantPageProps>().props;
    const { currentUrl, isCurrentUrl } = useCurrentUrl();

    const settingsUrl = competition
        ? settingsIndex.url({ competition: competition.slug })
        : null;
    const isInSettings = settingsUrl
        ? currentUrl.startsWith(settingsUrl)
        : false;
    const backUrl =
        competition && settingsUrl
            ? isCurrentUrl(settingsUrl)
                ? dashboard.url({ competition: competition.slug })
                : settingsUrl
            : null;

    return (
        <div className="bg-background text-foreground flex min-h-svh flex-col">
            <header className="bg-background/95 sticky top-0 z-10 border-b backdrop-blur">
                <div className="mx-auto flex w-full max-w-3xl items-center justify-between gap-3 px-4 py-3">
                    {isInSettings && backUrl ? (
                        <Button
                            asChild
                            variant="ghost"
                            size="sm"
                            className="-ml-2"
                        >
                            <Link href={backUrl}>
                                <ArrowLeft />
                                {t('Back')}
                            </Link>
                        </Button>
                    ) : (
                        <div className="flex min-w-0 items-center gap-2">
                            <AppLogoIcon className="size-7 shrink-0" />
                            {competition && (
                                <span className="truncate text-sm font-semibold">
                                    {competition.name}
                                </span>
                            )}
                        </div>
                    )}
                    <div className="flex items-center gap-1">
                        {settingsUrl && !isInSettings && (
                            <Button
                                asChild
                                variant="ghost"
                                size="sm"
                                aria-label={t('Settings')}
                            >
                                <Link href={settingsUrl} prefetch>
                                    <Settings />
                                    <span className="hidden sm:inline">
                                        {t('Settings')}
                                    </span>
                                </Link>
                            </Button>
                        )}
                        <Form action={logout().url} method="post">
                            {() => (
                                <Button
                                    type="submit"
                                    variant="ghost"
                                    size="sm"
                                    aria-label={t('Log out')}
                                >
                                    <LogOut />
                                    <span className="hidden sm:inline">
                                        {t('Log out')}
                                    </span>
                                </Button>
                            )}
                        </Form>
                    </div>
                </div>
            </header>
            <main className="mx-auto w-full max-w-3xl flex-1 px-4 py-4 sm:py-6">
                {children}
            </main>
        </div>
    );
}
