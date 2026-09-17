import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import ParticipantLayout from '@/layouts/participant/participant-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/'):
            // De foutpagina deelt de console-schil met de auth-pagina's: dat
            // is de enige layout die geen ingelogde gebruiker veronderstelt,
            // en een 403/404 treft net zo goed een uitgelogde bezoeker.
            case name.startsWith('errors/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            case name.startsWith('participant/'):
                return ParticipantLayout;
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        // Mirrors --color-copper from resources/css/app.css.
        color: '#b26a35',
    },
});

// This will set light / dark mode on load...
initializeTheme();
