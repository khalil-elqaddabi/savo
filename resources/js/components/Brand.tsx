/**
 * Savo brand mark — a confident geometric tile.
 * Three descending bars form an abstract "S" (money / flow mark).
 */
import { Link } from '@inertiajs/react';

export function BrandMark({
    size = 32,
    className = '',
}: {
    size?: number;
    className?: string;
}) {
    return (
        <svg
            width={size}
            height={size}
            viewBox="0 0 32 32"
            fill="none"
            className={className}
            aria-hidden="true"
        >
            <rect width="32" height="32" rx="9" fill="var(--color-accent)" />
            <rect
                x="8"
                y="20"
                width="16"
                height="4"
                rx="2"
                fill="var(--color-accent-contrast)"
                opacity="0.95"
            />
            <rect
                x="8"
                y="14"
                width="12"
                height="4"
                rx="2"
                fill="var(--color-accent-contrast)"
                opacity="0.85"
            />
            <rect
                x="8"
                y="8"
                width="8"
                height="4"
                rx="2"
                fill="var(--color-accent-contrast)"
                opacity="0.75"
            />
        </svg>
    );
}

/** Logo lockup: mark + wordmark + tagline. */
export function Brand({
    size = 32,
    withTagline = true,
    className = '',
    href = '/dashboard',
}: {
    size?: number;
    withTagline?: boolean;
    className?: string;
    href?: string;
}) {
    return (
        <Link
            href={href}
            className={`group flex items-center gap-2.5 ${className}`}
        >
            <BrandMark size={size} />
            <span className="flex flex-col leading-none">
                <span className="text-ink text-[17px] font-semibold tracking-tight">
                    Savo
                </span>
                {withTagline ? (
                    <span className="micro text-accent mt-1">Finance</span>
                ) : null}
            </span>
        </Link>
    );
}
