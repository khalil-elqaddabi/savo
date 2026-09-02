import type { ReactNode } from 'react';

type Tone = 'neutral' | 'success' | 'warning' | 'danger' | 'info' | 'brand';

const tones: Record<Tone, string> = {
    neutral: 'bg-surface-strong text-ink-soft',
    success: 'bg-mint/10 text-mint',
    warning: 'bg-amberbrand/10 text-amberbrand',
    danger: 'bg-coral/10 text-coral',
    info: 'bg-cyan-neon/10 text-accent',
    brand: 'bg-accent-soft text-accent',
};

interface BadgeProps {
    tone?: Tone;
    children?: ReactNode;
    className?: string;
}

export function Badge({
    tone = 'neutral',
    children,
    className = '',
}: BadgeProps) {
    return (
        <span className={`badge ${tones[tone]} ${className}`}>{children}</span>
    );
}
