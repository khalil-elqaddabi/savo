import { forwardRef } from 'react';
import { IconChevronDown } from './icons';

interface SelectProps extends React.SelectHTMLAttributes<HTMLSelectElement> {
    error?: string;
    label?: string;
    options: { value: string | number; label: string; disabled?: boolean }[];
    placeholder?: string;
}

export const Select = forwardRef<HTMLSelectElement, SelectProps>(
    function Select(
        { error, label, options, placeholder, className = '', id, ...rest },
        ref,
    ) {
        return (
            <div className="w-full">
                {label ? (
                    <label htmlFor={id} className="label">
                        {label}
                    </label>
                ) : null}
                <div className="relative">
                    <select
                        ref={ref}
                        id={id}
                        className={`input appearance-none pe-9 ${
                            error
                                ? 'border-coral focus:border-coral focus:ring-coral/25'
                                : ''
                        } ${className}`}
                        aria-invalid={error ? true : undefined}
                        {...rest}
                    >
                        {placeholder ? (
                            <option value="">{placeholder}</option>
                        ) : null}
                        {options.map((opt) => (
                            <option
                                key={opt.value}
                                value={opt.value}
                                disabled={opt.disabled}
                            >
                                {opt.label}
                            </option>
                        ))}
                    </select>
                    <IconChevronDown
                        className="text-ink-faint pointer-events-none absolute end-3 top-1/2 -translate-y-1/2"
                        size={16}
                    />
                </div>
                {error ? (
                    <p
                        className="text-coral mt-1 text-xs font-medium"
                        role="alert"
                    >
                        {error}
                    </p>
                ) : null}
            </div>
        );
    },
);
