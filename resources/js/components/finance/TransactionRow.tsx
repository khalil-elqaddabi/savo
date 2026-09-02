import { formatSigned } from '@/lib/money';
import type { AppInfo } from '@/types';
import { usePage } from '@inertiajs/react';
import { transactionTypeIcon } from './accountTypeIcon';

interface Tx {
    id: number;
    type: string;
    amount: string | number;
    date?: string;
    description?: string | null;
    account?: string | null;
    destination?: string | null;
    category?: string | null;
    category_icon?: string | null;
    category_color?: string | null;
}

export function TransactionRow({
    tx,
    currency = 'MAD',
}: {
    tx: Tx;
    currency?: string;
}) {
    const { app } = usePage<{ app: AppInfo }>().props;
    const { icon: Icon, color, meta } = transactionTypeIcon(tx.type);
    const isExpense = meta === 'expense';

    const title = tx.description || tx.category || 'Transaction';
    const arrow = app.isRtl ? '←' : '→';
    const accountLine =
        tx.type === 'transfer'
            ? `${tx.account ?? ''} ${arrow} ${tx.destination ?? ''}`
            : `${tx.category || ''}${tx.account ? ` · ${tx.account}` : ''}`;

    return (
        <div className="group hover:bg-surface-soft/60 flex items-center gap-3.5 px-2 py-3 transition">
            <div
                className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl transition ${
                    isExpense
                        ? 'bg-coral/10 text-coral'
                        : 'bg-mint/10 text-mint'
                }`}
            >
                <Icon className={color} size={18} />
            </div>
            <div className="min-w-0 flex-1">
                <p className="text-ink truncate text-sm font-medium">{title}</p>
                <p className="text-ink-faint truncate text-xs">{accountLine}</p>
            </div>
            <div className="shrink-0 text-end">
                <p
                    className={`text-sm font-semibold tabular-nums ${
                        isExpense ? 'text-ink' : 'text-mint'
                    }`}
                >
                    {formatSigned(tx.amount, tx.type as any, currency)}
                </p>
                {tx.date ? (
                    <p className="text-ink-faint text-xs">{tx.date}</p>
                ) : null}
            </div>
        </div>
    );
}
