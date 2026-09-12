import { Head, Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ChevronRight,
    LockKeyhole,
    ShieldCheck,
    SunMoon,
    User,
} from 'lucide-react';
import AppearanceTabs from '@/components/appearance-tabs';
import { useTranslations } from '@/hooks/use-translations';
import { edit as editPassword } from '@/routes/competition/settings/password';
import { edit as editProfile } from '@/routes/competition/settings/profile';
import { edit as editSecurity } from '@/routes/competition/settings/security';
import type { Auth } from '@/types';

type Props = {
    competition: { name: string; slug: string };
};

type PageProps = {
    auth: Auth;
};

export default function ParticipantSettings({ competition }: Props) {
    const { t } = useTranslations();
    const { auth } = usePage<PageProps>().props;

    const items: {
        title: string;
        description: string;
        icon: LucideIcon;
        href: string;
    }[] = [
        {
            title: t('Profile'),
            description: t('Your name and email address'),
            icon: User,
            href: editProfile.url({ competition: competition.slug }),
        },
        {
            title: t('Password'),
            description: t('Choose a new password'),
            icon: LockKeyhole,
            href: editPassword.url({ competition: competition.slug }),
        },
        {
            title: t('Security'),
            description: t('Extra security with an app or passkey'),
            icon: ShieldCheck,
            href: editSecurity.url({ competition: competition.slug }),
        },
    ];

    return (
        <>
            <Head title={t('Settings')} />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-lg font-semibold sm:text-xl">
                        {t('Settings')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {auth.user.display_name} · {auth.user.email}
                    </p>
                </div>

                <nav className="divide-y overflow-hidden rounded-xl border">
                    {items.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            className="active:bg-muted hover:bg-muted/60 flex min-h-14 items-center gap-3 px-4 py-3 transition-colors"
                        >
                            <item.icon className="text-muted-foreground size-5 shrink-0" />
                            <span className="flex min-w-0 flex-col">
                                <span className="text-sm font-medium">
                                    {item.title}
                                </span>
                                <span className="text-muted-foreground text-xs">
                                    {item.description}
                                </span>
                            </span>
                            <ChevronRight className="text-muted-foreground ml-auto size-4 shrink-0" />
                        </Link>
                    ))}
                </nav>

                <section className="flex flex-col gap-3 rounded-xl border p-4">
                    <h2 className="flex items-center gap-2 text-sm font-semibold">
                        <SunMoon className="size-4" />
                        {t('Appearance')}
                    </h2>
                    <p className="text-muted-foreground text-sm">
                        {t('Choose how the app looks on this device')}
                    </p>
                    <AppearanceTabs />
                </section>
            </div>
        </>
    );
}
