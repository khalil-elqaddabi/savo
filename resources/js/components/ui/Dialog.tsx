import { useEffect, useId, useRef } from 'react';

import { useTrans } from '@/lib/translation';
import { IconX } from './icons';

interface DialogProps {
    open: boolean;
    onClose: () => void;
    title?: React.ReactNode;
    children?: React.ReactNode;
    footer?: React.ReactNode;
    size?: 'sm' | 'md' | 'lg';
    closeOnBackdrop?: boolean;
    describedBy?: string;
}

const sizes = { sm: 'max-w-md', md: 'max-w-lg', lg: 'max-w-2xl' };

const FOCUSABLE =
    'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

function getFocusable(root: HTMLElement): HTMLElement[] {
    return Array.from(root.querySelectorAll<HTMLElement>(FOCUSABLE)).filter(
        (el) => el.offsetParent !== null,
    );
}

export function Dialog({
    open,
    onClose,
    title,
    children,
    footer,
    size = 'md',
    closeOnBackdrop = true,
    describedBy,
}: DialogProps) {
    const t = useTrans();
    const panelRef = useRef<HTMLDivElement>(null);
    const restoreRef = useRef<HTMLElement | null>(null);
    const onCloseRef = useRef(onClose);
    const titleId = useId();
    const bodyId = useId();

    useEffect(() => {
        onCloseRef.current = onClose;
    });

    useEffect(() => {
        if (!open) return;

        restoreRef.current = document.activeElement as HTMLElement | null;

        const focusPanel = () => {
            const panel = panelRef.current;
            if (!panel) return;
            const focusables = getFocusable(panel);
            (focusables[0] ?? panel).focus();
        };
        requestAnimationFrame(focusPanel);

        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && closeOnBackdrop) {
                e.stopPropagation();
                onCloseRef.current();
                return;
            }
            if (e.key !== 'Tab') return;
            const panel = panelRef.current;
            if (!panel) return;
            const focusables = getFocusable(panel);
            if (focusables.length === 0) {
                e.preventDefault();
                panel.focus();
                return;
            }
            const first = focusables[0];
            const last = focusables[focusables.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        };

        document.addEventListener('keydown', onKey, true);
        document.body.style.overflow = 'hidden';
        return () => {
            document.removeEventListener('keydown', onKey, true);
            document.body.style.overflow = '';
            restoreRef.current?.focus?.();
        };
    }, [open, closeOnBackdrop]);

    if (!open) return null;

    return (
        <div
            className="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby={titleId}
            aria-describedby={describedBy ?? bodyId}
        >
            <div
                className="bg-ink/30 absolute inset-0 backdrop-blur-[2px]"
                onClick={closeOnBackdrop ? onClose : undefined}
                aria-hidden="true"
            />
            <div
                ref={panelRef}
                tabIndex={-1}
                className={`relative z-10 w-full ${sizes[size]} animate-pop bg-surface shadow-pop sm:shadow-pop rounded-t-3xl outline-none sm:rounded-2xl`}
            >
                <div className="border-line flex items-center justify-between border-b px-5 py-4 sm:px-6">
                    <h3
                        id={titleId}
                        className="text-ink text-[15px] font-semibold tracking-tight"
                    >
                        {title}
                    </h3>
                    <button
                        type="button"
                        onClick={onClose}
                        className="text-ink-faint hover:bg-surface-strong hover:text-ink rounded-lg p-1 transition"
                        aria-label={t('common.close')}
                    >
                        <IconX size={18} />
                    </button>
                </div>
                <div
                    id={bodyId}
                    className="max-h-[70vh] overflow-y-auto px-5 py-5 sm:px-6"
                >
                    {children}
                </div>
                {footer ? (
                    <div className="border-line border-t px-5 py-4 sm:px-6">
                        {footer}
                    </div>
                ) : null}
            </div>
        </div>
    );
}
