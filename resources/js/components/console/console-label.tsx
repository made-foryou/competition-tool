import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

export default function ConsoleLabel({
    className,
    ...props
}: ComponentProps<'label'>) {
    return (
        <label
            className={cn(
                'text-console-text/60 font-mono text-[11px] tracking-[0.1em] uppercase',
                className,
            )}
            {...props}
        />
    );
}
