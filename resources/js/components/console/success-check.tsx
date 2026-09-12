/**
 * Animated success mark: a soft green ring that scales in with a drawn
 * check.
 */
export default function SuccessCheck() {
    return (
        <div className="relative mx-auto mt-1.5 mb-[22px] size-16">
            <span
                className="absolute inset-0 rounded-full border border-[rgba(47,125,91,0.4)] bg-[rgba(47,125,91,0.16)]"
                style={{ animation: 'made-ring 0.5s var(--ease-made) both' }}
            />
            <svg
                width="64"
                height="64"
                viewBox="0 0 64 64"
                fill="none"
                className="relative"
                aria-hidden
            >
                <polyline
                    points="22 33 29 40 43 25"
                    stroke="var(--color-console-success)"
                    strokeWidth="3"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeDasharray="36"
                    style={{
                        animation:
                            'made-check 0.55s var(--ease-made) 0.25s both',
                    }}
                />
            </svg>
        </div>
    );
}
