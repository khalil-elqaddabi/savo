import { useTrans } from '@/lib/translation';
import type { SharedProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { IconCheck, IconInfo, IconX } from '../Icons';

interface Flash {
    id: number;
    type: 'success' | 'error' | 'status';
    message: string;
}

export function FlashMessages() {
    const t = useTrans();
    const { flash } = usePage<SharedProps>().props;
    const [toasts, setToasts] = useState<Flash[]>([]);

    useEffect(() => {
        const items: Flash[] = [];
        if (flash?.success)
            items.push({
                id: Date.now(),
                type: 'success',
                message: flash.success,
            });
        if (flash?.status)
            items.push({
                id: Date.now(),
                type: 'status',
                message: flash.status,
            });
        if (flash?.error)
            items.push({
                id: Date.now() + 1,
                type: 'error',
                message: flash.error,
            });

        if (items.length) {
            setToasts((prev) => [...prev, ...items]);
            items.forEach((item) => {
                setTimeout(
                    () =>
                        setToasts((prev) =>
                            prev.filter((t) => t.id !== item.id),
                        ),
                    4000,
                );
            });
        }
    }, [flash]);

    if (!toasts.length) return null;

    const tone = {
        success: 'border-mint/25 bg-surface text-ink',
        error: 'border-coral/30 bg-surface text-ink',
        status: 'border-accent/25 bg-surface text-ink',
    };
    const iconTone = {
        success: 'text-mint',
        error: 'text-coral',
        status: 'text-accent',
    };
    const icon = {
        success: IconCheck,
        error: IconInfo,
        status: IconInfo,
    };

    return (
        <div
            className="fixed start-1/2 top-4 z-[60] flex w-[92%] max-w-sm -translate-x-1/2 flex-col gap-2"
            role="status"
            aria-live="polite"
        >
            {toasts.map((toast) => {
                const Icon = icon[toast.type];
                return (
                    <div
                        key={toast.id}
                        className={`animate-fade-up shadow-lift flex items-center gap-3 rounded-xl border px-4 py-3 text-sm ${tone[toast.type]}`}
                    >
                        <Icon
                            size={18}
                            className={`shrink-0 ${iconTone[toast.type]}`}
                        />
                        <span className="flex-1">{toast.message}</span>
                        <button
                            type="button"
                            onClick={() =>
                                setToasts((prev) =>
                                    prev.filter((x) => x.id !== toast.id),
                                )
                            }
                            className="text-ink-faint opacity-60 transition hover:opacity-100"
                            aria-label={t('common.dismiss')}
                        >
                            <IconX size={16} />
                        </button>
                    </div>
                );
            })}
        </div>
    );
}
