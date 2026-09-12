import { cn } from '@/lib/utils';

export default function Heading({
    title,
    description,
    variant = 'default',
    as: Tag = 'h2',
    className,
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
    /** Heading level to render. Defaults to `h2` for backwards compatibility. */
    as?: 'h1' | 'h2' | 'h3';
    className?: string;
}) {
    return (
        <header
            className={cn(
                variant === 'small' ? '' : 'mb-8 space-y-0.5',
                className,
            )}
        >
            <Tag
                className={
                    variant === 'small'
                        ? 'mb-0.5 text-base font-medium'
                        : 'text-xl font-semibold tracking-tight'
                }
            >
                {title}
            </Tag>
            {description && (
                <p className="text-muted-foreground text-sm">{description}</p>
            )}
        </header>
    );
}
