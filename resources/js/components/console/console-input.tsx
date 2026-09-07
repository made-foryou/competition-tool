import type { ComponentProps, ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = ComponentProps<'input'> & {
    icon?: ReactNode;
};

/**
 * Dark console text input with an optional leading icon and copper focus ring.
 */
export default function ConsoleInput({ icon, className, ...props }: Props) {
    return (
        <div className="relative flex items-center">
            {icon && (
                <span className="text-console-faint pointer-events-none absolute left-[13px] flex">
                    {icon}
                </span>
            )}
            <input
                className={cn(
                    'bg-console-surface border-console-input-border rounded-console-sm text-console-text placeholder:text-console-text/38 focus:border-copper w-full border px-3.5 py-3 font-sans text-[15px] transition-[border-color,box-shadow] duration-200 outline-none focus:shadow-[0_0_0_3px_rgba(178,106,53,0.2)]',
                    icon && 'pl-[42px]',
                    className,
                )}
                {...props}
            />
        </div>
    );
}
