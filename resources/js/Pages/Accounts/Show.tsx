import AppLayout from '@/Layouts/AppLayout';
import { IconPlus, IconTransfer, IconWallet } from '@/components/Icons';
import { TransactionFormDialog } from '@/components/finance/TransactionFormDialog';
import { TransactionRow } from '@/components/finance/TransactionRow';
import {
    AccountTypeIcon,
    accountTypeMeta,
} from '@/components/finance/accountTypeIcon';
import { Button, Card, EmptyState } from '@/components/ui';
import { PageHeader } from '@/components/ui/PageHeader';
import { formatMoney } from '@/lib/money';
import { useTrans } from '@/lib/translation';
import type { SharedProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface Account {
    id: number;
    name: string;
    type: string;
    balance: string;
    starting_balance?: string;
    icon?: string | null;
    color?: string | null;
    description?: string | null;
    currency?: string;
    institution?: string | null;
}

interface Tx {
    id: number;
    type: string;
    amount: string;
    date?: string;
    description?: string | null;
    account?: string | null;
    destination?: string | null;
    category?: string | null;
    category_icon?: string | null;
    category_color?: string | null;
}

interface Props extends SharedProps {
    account: Account;
    transactions: Tx[];
}

export default function AccountsShow() {
    const t = useTrans();
    const { app, account, transactions } = usePage<Props>().props;
    const currency = account.currency || app.currency;
    const meta = accountTypeMeta[account.type];
    const hue = account.color ?? meta?.hue ?? 'var(--color-accent)';

    const [addMoneyOpen, setAddMoneyOpen] = useState(false);

    return (
        <AppLayout>
            <PageHeader
                title={account.name}
                subtitle={account.institution || t('accounts.details')}
                action={
                    <div className="flex items-center gap-2">
                        <Button onClick={() => setAddMoneyOpen(true)}>
                            <IconPlus size={16} />
                            {t('accounts.add_money')}
                        </Button>
                        <Link
                            href={`/transactions?account=${account.id}`}
                            className="btn-secondary"
                        >
                            <IconTransfer size={16} />
                            {t('common.view_all')}
                        </Link>
                    </div>
                }
            />

            {/* Account hero — refined, balance-first */}
            <section className="border-line bg-surface shadow-card mb-6 overflow-hidden rounded-2xl border">
                <div className="grid lg:grid-cols-[1.3fr_1fr]">
                    <div className="p-6 sm:p-8">
                        <span
                            className="flex h-11 w-11 items-center justify-center rounded-xl"
                            style={{ backgroundColor: `${hue}1f`, color: hue }}
                        >
                            <AccountTypeIcon type={account.type} size={24} />
                        </span>
                        <p className="micro mt-6">{t('accounts.balance')}</p>
                        <p className="text-ink mt-1 text-4xl font-semibold tracking-tight tabular-nums sm:text-5xl">
                            {formatMoney(account.balance, { currency })}
                        </p>
                        {account.description ? (
                            <p className="text-ink-soft mt-3 max-w-md text-sm">
                                {account.description}
                            </p>
                        ) : null}
                    </div>

                    <div className="border-line border-t lg:border-s lg:border-t-0">
                        <div className="divide-line grid grid-cols-1 divide-y">
                            <div className="flex items-center justify-between px-6 py-4 sm:px-8">
                                <span className="text-ink-faint text-sm">
                                    {t('accounts.starting_balance')}
                                </span>
                                <span className="text-ink text-[15px] font-semibold tabular-nums">
                                    {formatMoney(
                                        account.starting_balance ?? 0,
                                        { currency },
                                    )}
                                </span>
                            </div>
                            <div className="flex items-center justify-between px-6 py-4 sm:px-8">
                                <span className="text-ink-faint text-sm">
                                    {t('accounts.type')}
                                </span>
                                <span className="text-ink text-[15px] font-medium">
                                    {t(`accounts.type_${account.type}`)}
                                </span>
                            </div>
                            <div className="flex items-center justify-between px-6 py-4 sm:px-8">
                                <span className="text-ink-faint text-sm">
                                    {t('accounts.total_tx')}
                                </span>
                                <span className="text-ink text-[15px] font-semibold tabular-nums">
                                    {transactions.length}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <Card title={t('accounts.transactions')}>
                {transactions.length === 0 ? (
                    <EmptyState
                        icon={<IconWallet size={28} />}
                        title={t('accounts.no_tx')}
                        description={t('accounts.no_tx_hint')}
                        action={
                            <Link href="/transactions" className="btn-primary">
                                <IconPlus size={16} />
                                {t('common.add')}
                            </Link>
                        }
                    />
                ) : (
                    <div className="divide-line divide-y">
                        {transactions.map((tx) => (
                            <TransactionRow
                                key={tx.id}
                                tx={tx}
                                currency={currency}
                            />
                        ))}
                    </div>
                )}
            </Card>

            <TransactionFormDialog
                open={addMoneyOpen}
                onClose={() => setAddMoneyOpen(false)}
                fixedType="income"
                title={t('accounts.add_money')}
                initialAccountId={account.id}
            />
        </AppLayout>
    );
}
