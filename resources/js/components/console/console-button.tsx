import { cva, type VariantProps } from 'class-variance-authority';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

const consoleButtonVariants = cva(
    'rounded-console-sm inline-flex cursor-pointer items-center justify-center gap-2 font-sans leading-tight font-medium transition-[transform,background,filter,box-shadow,border-color] duration-200 ease-[var(--ease-made)] disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0 disabled:hover:filter-none',
    {
        variants: {
            variant: {
                cta: 'bg-copper text-white shadow-[0_1px_2px_rgba(178,106,53,0.24)] hover:-translate-y-0.5 hover:brightness-95',
                secondary:
                    'border-console-input-border text-console-text hover:border-console-text/40 hover:bg-console-surface border bg-transparent hover:-translate-y-0.5',
                ghost: 'text-console-muted hover:bg-console-surface hover:text-console-text bg-transparent',
            },
            size: {
                md: 'px-[22px] py-[11px] text-[15px]',
                lg: 'px-7 py-3.5 text-[17px]',
            },
        },
        defaultVariants: {
            variant: 'cta',
            size: 'md',
        },
    },
);

type Props = ComponentProps<'button'> &
    VariantProps<typeof consoleButtonVariants>;

/**
 * Made console button. Copper `cta` is the single inviting call-to-action per
 * screen; lifts 2px on hover with the Made easing.
 */
export default function ConsoleButton({
    className,
    variant,
    size,
    type = 'button',
    ...props
}: Props) {
    return (
        <button
            type={type}
            className={cn(consoleButtonVariants({ variant, size }), className)}
            {...props}
        />
    );
}
