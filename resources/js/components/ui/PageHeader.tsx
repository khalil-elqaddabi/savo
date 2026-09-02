import type { ReactNode } from 'react';

interface Props {
    title: string;
    subtitle?: string;
    action?: ReactNode;
}

export function PageHeader({ title, subtitle, action }: Props) {
    return (
        <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div className="min-w-0">
                <h1 className="text-ink text-2xl font-semibold tracking-tight sm:text-[28px]">
                    {title}
                </h1>
                {subtitle ? (
                    <p className="text-ink-faint mt-1 text-sm">{subtitle}</p>
                ) : null}
            </div>
            {action ? (
                <div className="flex shrink-0 items-center gap-2">{action}</div>
            ) : null}
        </div>
    );
}
