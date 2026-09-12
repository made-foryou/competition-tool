import type { ReactNode } from 'react';
import { useTranslations } from '@/hooks/use-translations';

/**
 * Full-screen dark console backdrop: ambient glows, centered content and the
 * operational status line. All entrance animations inside the shell respect
 * prefers-reduced-motion via the `made-motion` kill-switch class.
 */
export default function ConsoleShell({ children }: { children: ReactNode }) {
    const { t } = useTranslations();

    return (
        <section className="made-motion bg-console-bg relative flex min-h-svh w-full items-center justify-center overflow-hidden px-6 py-12 font-sans">
            <div
                className="pointer-events-none absolute -top-[18%] -right-[12%] size-[75vw] max-h-[900px] max-w-[900px]"
                style={{
                    background:
                        'radial-gradient(circle, rgba(178,106,53,0.34), transparent 60%)',
                    animation:
                        'made-bg-in 1s var(--ease-made) both, made-glow 9s ease-in-out 1.1s infinite alternate',
                }}
            />
            <div
                className="pointer-events-none absolute -bottom-[22%] -left-[14%] size-[70vw] max-h-[820px] max-w-[820px]"
                style={{
                    background:
                        'radial-gradient(circle, rgba(47,92,76,0.55), transparent 58%)',
                    animation:
                        'made-bg-in 1.1s var(--ease-made) both, made-glow 11s ease-in-out 1.3s infinite alternate',
                }}
            />

            {children}

            <div
                className="made-anim text-console-text/50 absolute right-0 bottom-6 left-0 flex items-center justify-center gap-2 font-mono text-[11px] tracking-[0.05em]"
                style={{
                    animationDuration: '0.6s',
                    '--made-delay': '1.5s',
                }}
            >
                <span className="animate-made-pulse bg-console-success size-[7px] rounded-full" />
                {t('operational · 99.98% uptime')} · v1.0.0
            </div>
        </section>
    );
}
