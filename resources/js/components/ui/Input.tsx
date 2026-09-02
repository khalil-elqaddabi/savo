import { forwardRef } from 'react';

interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
    error?: string;
    label?: string;
    hint?: string;
}

export const Input = forwardRef<HTMLInputElement, InputProps>(function Input(
    { error, label, hint, className = '', id, ...rest },
    ref,
) {
    return (
        <div className="w-full">
            {label ? (
                <label htmlFor={id} className="label">
                    {label}
                </label>
            ) : null}
            <input
                ref={ref}
                id={id}
                className={`input ${
                    error
                        ? 'border-coral focus:border-coral focus:ring-coral/25'
                        : ''
                } ${className}`}
                aria-invalid={error ? true : undefined}
                {...rest}
            />
            {error ? (
                <p className="text-coral mt-1 text-xs font-medium" role="alert">
                    {error}
                </p>
            ) : hint ? (
                <p className="text-ink-faint mt-1 text-xs">{hint}</p>
            ) : null}
        </div>
    );
});
