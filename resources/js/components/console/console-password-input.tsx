import { Eye, EyeOff, LockKeyhole } from 'lucide-react';
import type { ComponentProps } from 'react';
import { useState } from 'react';
import ConsoleInput from '@/components/console/console-input';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';

type Props = Omit<ComponentProps<typeof ConsoleInput>, 'type' | 'icon'>;

/**
 * Console password field: lock icon on the left, show/hide toggle on the
 * right.
 */
export default function ConsolePasswordInput({ className, ...props }: Props) {
    const { t } = useTranslations();
    const [showPassword, setShowPassword] = useState(false);

    return (
        <div className="relative">
            <ConsoleInput
                type={showPassword ? 'text' : 'password'}
                icon={<LockKeyhole size={18} strokeWidth={1.6} />}
                className={cn('pr-11', className)}
                {...props}
            />
            <button
                type="button"
                onClick={() => setShowPassword((previous) => !previous)}
                aria-label={t('Show or hide password')}
                tabIndex={-1}
                className="text-console-text/55 hover:text-console-text absolute inset-y-0 right-2 my-auto flex size-[30px] cursor-pointer items-center justify-center rounded-md transition-colors"
            >
                {showPassword ? (
                    <EyeOff size={18} strokeWidth={1.6} />
                ) : (
                    <Eye size={18} strokeWidth={1.6} />
                )}
            </button>
        </div>
    );
}
