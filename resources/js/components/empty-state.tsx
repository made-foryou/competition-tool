import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    icon: LucideIcon;
    title: string;
    description?: string;
    /** CTA, bijvoorbeeld een `Button` (asChild met een `Link`) of `null` als het formulier eronder al de actie is. */
    action?: ReactNode;
    /** `sm` voor compacte contexten zoals dashboardkaarten. */
    size?: 'default' | 'sm';
    className?: string;
};

/**
 * Compacte lege-staat voor admin-lijsten: icoon in een cirkel, titel,
 * optionele uitleg en optionele CTA. Geen marketing-achtige illustraties.
 */
export default function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    size = 'default',
    className,
}: Props) {
    const isCompact = size === 'sm';

    return (
        <div
            className={cn(
                'flex flex-col items-center gap-3 rounded-xl border border-dashed text-center',
                isCompact ? 'gap-2 py-6' : 'py-8',
                className,
            )}
        >
            <div
                className={cn(
                    'bg-muted flex items-center justify-center rounded-full',
                    isCompact ? 'size-8' : 'size-10',
                )}
            >
                <Icon
                    className={cn(
                        'text-muted-foreground',
                        isCompact ? 'size-4' : 'size-5',
                    )}
                />
            </div>
            <div className="space-y-1 px-4">
                <p className="text-sm font-medium">{title}</p>
                {description && (
                    <p className="text-muted-foreground mx-auto max-w-sm text-sm">
                        {description}
                    </p>
                )}
            </div>
            {action}
        </div>
    );
}
