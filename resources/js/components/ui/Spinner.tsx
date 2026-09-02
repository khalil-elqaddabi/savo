import { useTrans } from '@/lib/translation';
import type { ReactNode } from 'react';
import { IconSpinner } from './icons';

export function Spinner({
    size = 24,
    className = '',
}: {
    size?: number;
    className?: string;
}) {
    return (
        <IconSpinner
            size={size}
            className={`text-accent animate-spin ${className}`}
        />
    );
}

export function PageLoading({ label }: { label?: string }) {
    const t = useTrans();
    return (
        <div
            className="flex flex-col items-center justify-center gap-3 py-24"
            role="status"
            aria-label={label ?? t('common.loading')}
        >
            <Spinner size={28} />
            {label ? <p className="text-ink-faint text-sm">{label}</p> : null}
        </div>
    );
}

interface EmptyStateProps {
    icon?: ReactNode;
    title: string;
    description?: string;
    action?: ReactNode;
}

export function EmptyState({
    icon,
    title,
    description,
    action,
}: EmptyStateProps) {
    return (
        <div className="border-line-strong flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed px-6 py-16 text-center">
            {icon ? (
                <div className="border-line bg-surface-soft text-ink-faint flex h-14 w-14 items-center justify-center rounded-2xl border">
                    {icon}
                </div>
            ) : null}
            <h3 className="text-ink text-[15px] font-semibold">{title}</h3>
            {description ? (
                <p className="text-ink-faint max-w-sm text-sm">{description}</p>
            ) : null}
            {action ? <div className="mt-2">{action}</div> : null}
        </div>
    );
}
