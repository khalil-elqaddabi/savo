import type { ReactNode } from 'react';

interface StatCardProps {
    label: string;
    value: string;
    icon?: ReactNode;
    delta?: number | null;
    deltaLabel?: string;
    positive?: boolean;
    accent?: string;
    hint?: string;
}

export function StatCard({
    label,
    value,
    icon,
    delta,
    deltaLabel,
    positive = true,
    accent = 'var(--color-accent)',
    hint,
}: StatCardProps) {
    const showDelta = delta !== undefined && delta !== null;
    const deltaUp = (delta ?? 0) >= 0;
    const isGood = positive ? deltaUp : !deltaUp;

    return (
        <div className="group card hover:border-line-strong hover:shadow-lift relative overflow-hidden p-4 transition duration-200 sm:p-5">
            <div className="flex items-center justify-between gap-2">
                <p className="micro">{label}</p>
                {icon ? (
                    <span
                        className="text-accent flex h-8 w-8 items-center justify-center rounded-lg"
                        style={{ backgroundColor: 'var(--color-accent-soft)' }}
                    >
                        {icon}
                    </span>
                ) : null}
            </div>
            <p className="text-ink mt-3 text-2xl font-semibold tracking-tight tabular-nums">
                {value}
            </p>
            {showDelta ? (
                <div className="mt-2 flex items-center gap-1.5 text-xs font-medium">
                    <span
                        className={`inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-[11px] font-semibold ${
                            isGood
                                ? 'bg-mint/10 text-mint'
                                : 'bg-coral/10 text-coral'
                        }`}
                    >
                        {deltaUp ? '↑' : '↓'} {Math.abs(delta ?? 0)}%
                    </span>
                    {deltaLabel ? (
                        <span className="text-ink-faint text-[11px]">
                            {deltaLabel}
                        </span>
                    ) : null}
                </div>
            ) : hint ? (
                <p className="text-ink-faint mt-2 text-[11px]">{hint}</p>
            ) : null}
        </div>
    );
}
