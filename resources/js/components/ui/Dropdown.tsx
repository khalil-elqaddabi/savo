import { useEffect, useRef, useState, type ReactNode } from 'react';

interface Item {
    label: string;
    icon?: ReactNode;
    onClick?: () => void;
    destructive?: boolean;
    disabled?: boolean;
}

interface DropdownProps {
    trigger: ReactNode;
    items: Item[];
    align?: 'start' | 'end';
    label?: string;
}

/** Lightweight menu dropdown with outside-click + escape handling. */
export function Dropdown({
    trigger,
    items,
    align = 'end',
    label,
}: DropdownProps) {
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;
        const onDoc = (e: MouseEvent) => {
            if (ref.current && !ref.current.contains(e.target as Node)) {
                setOpen(false);
            }
        };
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') setOpen(false);
        };
        document.addEventListener('mousedown', onDoc);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onDoc);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    return (
        <div className="relative inline-flex" ref={ref}>
            <div
                onClick={() => setOpen((o) => !o)}
                aria-haspopup="menu"
                aria-expanded={open}
            >
                {trigger}
            </div>
            {open ? (
                <div
                    role="menu"
                    aria-label={label}
                    className={`animate-pop border-line bg-surface-elevated shadow-pop absolute z-40 mt-1.5 min-w-[10rem] overflow-hidden rounded-xl border p-1 ${
                        align === 'end' ? 'end-0' : 'start-0'
                    }`}
                >
                    {items.map((item, i) => (
                        <button
                            key={i}
                            type="button"
                            role="menuitem"
                            disabled={item.disabled}
                            onClick={() => {
                                setOpen(false);
                                item.onClick?.();
                            }}
                            className={`flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-[13px] font-medium transition ${
                                item.destructive
                                    ? 'text-coral hover:bg-coral/10'
                                    : 'text-ink-soft hover:bg-surface-strong hover:text-ink'
                            } disabled:cursor-not-allowed disabled:opacity-50`}
                        >
                            {item.icon ? (
                                <span className="text-ink-faint">
                                    {item.icon}
                                </span>
                            ) : null}
                            {item.label}
                        </button>
                    ))}
                </div>
            ) : null}
        </div>
    );
}
