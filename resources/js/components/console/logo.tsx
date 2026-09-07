import { cn } from '@/lib/utils';

const SIZES = {
    sm: 20,
    md: 30,
    lg: 44,
} as const;

type Props = {
    size?: keyof typeof SIZES | number;
    tone?: 'primary' | 'reversed';
    blink?: boolean;
    className?: string;
};

/**
 * The Made logo — wordmark in IBM Plex Serif SemiBold with the copper cursor.
 */
export default function Logo({
    size = 'md',
    tone = 'primary',
    blink = false,
    className,
}: Props) {
    const height = typeof size === 'number' ? size : SIZES[size];

    return (
        <span className={cn('inline-flex items-baseline', className)}>
            <span
                className={cn(
                    'font-serif leading-[0.9] font-semibold tracking-[-0.02em]',
                    tone === 'reversed'
                        ? 'text-console-text'
                        : 'text-made-green',
                )}
                style={{ fontSize: height }}
            >
                Made
            </span>
            <span
                className={cn(
                    'bg-copper inline-block rounded-[2px]',
                    blink && 'animate-made-caret-blink',
                )}
                style={{
                    width: Math.max(2, height * 0.108),
                    height: height * 0.72,
                    marginLeft: Math.max(2, height * 0.1),
                }}
            />
        </span>
    );
}
