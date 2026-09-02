import type { HTMLAttributes, ReactNode } from 'react';

interface CardProps extends HTMLAttributes<HTMLDivElement> {
    children?: ReactNode;
    title?: ReactNode;
    subtitle?: ReactNode;
    action?: ReactNode;
    padding?: boolean;
    highlighted?: boolean;
}

export function Card({
    title,
    subtitle,
    action,
    children,
    className = '',
    padding = true,
    highlighted = false,
    ...rest
}: CardProps) {
    return (
        <div
            className={`card relative overflow-hidden transition duration-200 ${
                highlighted
                    ? 'glow-brand border-accent/30'
                    : 'hover:border-line-strong hover:shadow-lift'
            } ${padding ? 'p-5' : ''} ${className}`}
            {...rest}
        >
            {title || action ? (
                <div
                    className={`flex items-start justify-between gap-3 ${
                        padding ? 'mb-4' : 'mb-4 px-5 pt-5'
                    }`}
                >
                    <div>
                        {title ? (
                            <h3 className="text-ink text-[15px] font-semibold tracking-tight">
                                {title}
                            </h3>
                        ) : null}
                        {subtitle ? (
                            <p className="text-ink-faint mt-0.5 text-xs">
                                {subtitle}
                            </p>
                        ) : null}
                    </div>
                    {action ? <div className="shrink-0">{action}</div> : null}
                </div>
            ) : null}
            {children}
        </div>
    );
}
