import { Form, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, LogOut, Settings } from 'lucide-react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useTranslations } from '@/hooks/use-translations';
import { logout } from '@/routes';
import { dashboard, matches } from '@/routes/competition';
import { index as settingsIndex } from '@/routes/competition/settings';

type ParticipantPageProps = {
    competition?: { name: string; slug: string };
    availabilityState?: string;
    hasSubmitted?: boolean;
};

/**
 * Shared shell for the participant-facing pages under `participant/*`:
 * a slim header with the app logo, the active competition name and a
 * logout button, and a max-width, mobile-first content area. Inside the
 * settings section and on the matches page the logo turns into a back button
 * (settings back to the previous settings page or the dashboard, matches to
 * the dashboard); the settings button stays visible on the matches page. The
 * settings button is hidden while the availability still has to be submitted.
 */
export default function ParticipantLayout({
    children,
}: {
    children: ReactNode;
}) {
    const { t } = useTranslations();
    const { competition, availabilityState, hasSubmitted } =
        usePage<ParticipantPageProps>().props;
    const { currentUrl, isCurrentUrl } = useCurrentUrl();

    // Zolang de beschikbaarheid nog ingediend moet worden, houdt
    // EnsureAvailabilityIsSubmitted de instellingen dicht: de knop zou
    // alleen terugsturen naar dit formulier.
    const mustSubmitAvailability =
        availabilityState === 'open' && hasSubmitted === false;

    const settingsUrl = competition
        ? settingsIndex.url({ competition: competition.slug })
        : null;
    const isInSettings = settingsUrl
        ? currentUrl.startsWith(settingsUrl)
        : false;
    const matchesUrl = competition
        ? matches.url({ competition: competition.slug })
        : null;
    const isOnMatches = matchesUrl ? isCurrentUrl(matchesUrl) : false;
    const dashboardUrl = competition
        ? dashboard.url({ competition: competition.slug })
        : null;
    const backUrl = isOnMatches
        ? dashboardUrl
        : competition && settingsUrl
          ? isCurrentUrl(settingsUrl)
              ? dashboardUrl
              : settingsUrl
          : null;

    return (
        <div className="bg-background text-foreground flex min-h-svh flex-col">
            <header className="bg-background/95 sticky top-0 z-10 border-b backdrop-blur">
                <div className="mx-auto flex w-full max-w-3xl items-center justify-between gap-3 px-4 py-3">
                    {(isInSettings || isOnMatches) && backUrl ? (
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
                        {settingsUrl &&
                            !isInSettings &&
                            !mustSubmitAvailability && (
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
