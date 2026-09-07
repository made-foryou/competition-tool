import { cn } from '@/lib/utils';

type Props = {
    message?: string;
    className?: string;
};

/**
 * Validation error line under a console form field, tinted for the dark
 * background.
 */
export default function ConsoleError({ message, className }: Props) {
    return message ? (
        <p className={cn('text-console-error text-[13px]', className)}>
            {message}
        </p>
    ) : null;
}
