import type { ReactNode } from 'react';
import Logo from '@/components/console/logo';
import { cn } from '@/lib/utils';

type Props = {
    label: string;
    children: ReactNode;
    className?: string;
};

/**
 * The glassy console card: terminal header with dots and a mono screen label,
 * a one-time light sweep, and the Made logo + ADMIN tag above the content.
 */
export default function ConsoleCard({ label, children, className }: Props) {
    return (
        <div
            className={cn(
                'bg-console-surface border-console-border relative z-1 w-[min(440px,92vw)] overflow-hidden rounded-2xl border shadow-[0_40px_90px_-40px_rgba(0,0,0,0.7)]',
                className,
            )}
            style={{ animation: 'made-rise-card 0.75s var(--ease-made) both' }}
        >
            <div
                className="pointer-events-none absolute inset-0"
                style={{
                    background:
                        'linear-gradient(105deg, transparent 32%, rgba(244,240,231,0.10) 48%, transparent 64%)',
                    animation: 'made-sweep 1.35s var(--ease-made) 0.55s both',
                }}
            />

            <div className="border-console-border/70 relative flex items-center justify-between border-b px-[18px] py-3.5">
                <span className="flex gap-[7px]">
                    <span className="bg-copper size-[11px] rounded-full" />
                    <span className="bg-sage size-[11px] rounded-full" />
                    <span className="bg-console-text/20 size-[11px] rounded-full" />
                </span>
                <span className="text-console-text/40 font-mono text-[10.5px] tracking-[0.1em] uppercase">
                    made-console · {label}
                </span>
            </div>

            <div className="relative px-9 pt-[34px] pb-[30px]">
                <div
                    className="made-anim mb-6 flex items-center justify-between"
                    style={{ '--made-delay': '0.55s' }}
                >
                    <Logo size="md" tone="reversed" blink />
                    <span className="text-copper font-mono text-[10.5px] tracking-[0.12em] uppercase">
                        Admin
                    </span>
                </div>

                {children}
            </div>
        </div>
    );
}
