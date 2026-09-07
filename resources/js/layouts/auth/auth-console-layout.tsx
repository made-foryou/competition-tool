import ConsoleCard from '@/components/console/console-card';
import ConsoleShell from '@/components/console/console-shell';
import type { AuthLayoutProps } from '@/types';

/**
 * Made console auth shell: always-dark backdrop with the glassy terminal
 * card. The `label` layout prop sets the mono screen label in the card
 * header (e.g. "secure-login").
 */
export default function AuthConsoleLayout({
    label = 'console',
    children,
}: AuthLayoutProps) {
    return (
        <ConsoleShell>
            <ConsoleCard label={label}>{children}</ConsoleCard>
        </ConsoleShell>
    );
}
