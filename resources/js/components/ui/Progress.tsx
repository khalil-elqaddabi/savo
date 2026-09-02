type Tone = 'success' | 'warning' | 'danger' | 'brand';

const toneColor: Record<Tone, string> = {
    success: 'bg-mint',
    warning: 'bg-amberbrand',
    danger: 'bg-coral',
    brand: 'bg-accent',
};

interface ProgressProps {
    value: number;
    tone?: Tone;
    className?: string;
    showLabel?: boolean;
}

export function Progress({
    value,
    tone = 'brand',
    className = '',
    showLabel = false,
}: ProgressProps) {
    const clamped = Math.max(0, Math.min(100, value));

    return (
        <div className="flex items-center gap-2.5">
            <div
                className={`bg-surface-strong h-1.5 w-full overflow-hidden rounded-full ${className}`}
                role="progressbar"
                aria-valuenow={Math.round(clamped)}
                aria-valuemin={0}
                aria-valuemax={100}
            >
                <div
                    className={`h-full rounded-full transition-all duration-500 ease-out ${toneColor[tone]}`}
                    style={{ width: `${clamped}%` }}
                />
            </div>
            {showLabel ? (
                <span className="text-ink-soft shrink-0 text-xs font-semibold tabular-nums">
                    {Math.round(clamped)}%
                </span>
            ) : null}
        </div>
    );
}
