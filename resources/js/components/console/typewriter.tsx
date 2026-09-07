type Props = {
    text: string;
};

/**
 * The mono typewriter line under a console heading, e.g. "> authenticatie
 * vereist". Types itself in with a blinking caret; static under
 * prefers-reduced-motion.
 */
export default function Typewriter({ text }: Props) {
    return (
        <div className="h-[18px]">
            <span
                className="made-typewriter text-console-muted font-mono text-[12.5px] tracking-[0.02em]"
                style={{
                    '--made-type-width': `${text.length + 0.5}ch`,
                    '--made-type-steps': text.length,
                }}
            >
                {text}
            </span>
        </div>
    );
}
