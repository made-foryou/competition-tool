import Typewriter from '@/components/console/typewriter';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    typewriter: string;
    className?: string;
};

/**
 * Serif console heading with the typewriter line below it.
 */
export default function ConsoleHeading({
    title,
    typewriter,
    className,
}: Props) {
    return (
        <div className={cn('mb-6', className)}>
            <h1
                className="text-console-text made-anim mb-[9px] font-serif text-[27px] font-semibold tracking-[-0.01em]"
                style={{ '--made-delay': '0.68s' }}
            >
                {title}
            </h1>
            <Typewriter text={typewriter} />
        </div>
    );
}
