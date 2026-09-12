import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

export default function InputError({
    id,
    message,
    className = '',
    ...props
}: HTMLAttributes<HTMLParagraphElement> & { id?: string; message?: string }) {
    return message ? (
        <p
            {...props}
            id={id}
            role="alert"
            className={cn('text-destructive-foreground text-sm', className)}
        >
            {message}
        </p>
    ) : null;
}
