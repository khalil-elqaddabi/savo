import { formatMoney } from '@/lib/money';
import { useTrans } from '@/lib/translation';
import { Link } from '@inertiajs/react';
import { AccountTypeIcon, accountTypeMeta } from './accountTypeIcon';

interface Account {
    id: number;
    name: string;
    type: string;
    balance: string | number;
    icon?: string | null;
    color?: string | null;
    description?: string | null;
    transactions_count?: number;
}

export function AccountCard({
    account,
    currency = 'MAD',
    link = true,
    compact = false,
}: {
    account: Account;
    currency?: string;
    link?: boolean;
    compact?: boolean;
}) {
    const t = useTrans();
    const meta = accountTypeMeta[account.type];
    const hue = account.color ?? meta?.hue ?? 'var(--color-accent)';

    const content = (
        <div className="group card hover:border-line-strong hover:shadow-lift relative overflow-hidden p-4 transition duration-200">
            <div className="flex items-center gap-3">
                <span
                    className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                    style={{ backgroundColor: `${hue}1f`, color: hue }}
                >
                    <AccountTypeIcon type={account.type} size={19} />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="text-ink truncate text-sm font-medium">
                        {account.name}
                    </p>
                    <p className="text-ink-faint text-xs">
                        {t(`accounts.type_${account.type}`)}
                    </p>
                </div>
            </div>

            <div className="mt-4">
                <p className="micro">{t('accounts.balance')}</p>
                <p
                    className={`text-ink mt-0.5 font-semibold tracking-tight tabular-nums ${
                        compact ? 'text-xl' : 'text-2xl'
                    }`}
                >
                    {formatMoney(account.balance, { currency })}
                </p>
            </div>
        </div>
    );

    return link ? (
        <Link
            href={`/accounts/${account.id}`}
            className="block focus-visible:outline-none"
        >
            {content}
        </Link>
    ) : (
        content
    );
}
