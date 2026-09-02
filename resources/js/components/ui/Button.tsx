import type { ButtonHTMLAttributes, ReactNode } from 'react';
import { IconSpinner } from './icons';

type Variant = 'primary' | 'secondary' | 'soft' | 'ghost' | 'danger';
type Size = 'sm' | 'md' | 'lg';

interface Props extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: Variant;
    size?: Size;
    loading?: boolean;
    fullWidth?: boolean;
    children?: ReactNode;
}

const variants: Record<Variant, string> = {
    primary: 'bg-accent text-accent-contrast hover:bg-accent-strong',
    secondary:
        'border border-line-strong bg-surface-elevated text-ink-soft hover:border-line-soft hover:bg-surface-strong hover:text-ink',
    soft: 'bg-accent-soft text-accent hover:bg-accent/15',
    ghost: 'text-ink-soft hover:bg-surface-strong hover:text-ink',
    danger: 'bg-coral text-white hover:opacity-90',
};

const sizes: Record<Size, string> = {
    sm: 'px-3 py-1.5 text-xs',
    md: 'px-4 py-2.5 text-sm',
    lg: 'px-5 py-3 text-[15px]',
};

export function Button({
    variant = 'primary',
    size = 'md',
    loading = false,
    fullWidth = false,
    children,
    className = '',
    disabled,
    ...rest
}: Props) {
    return (
        <button
            className={`btn ${variants[variant]} ${sizes[size]} ${
                fullWidth ? 'w-full' : ''
            } ${className}`}
            disabled={disabled || loading}
            {...rest}
        >
            {loading ? <IconSpinner className="animate-spin" /> : null}
            {children}
        </button>
    );
}
