import AppLayout from '@/Layouts/AppLayout';
import {
    IconFilter,
    IconPlus,
    IconSearch,
    IconTrash,
} from '@/components/Icons';
import { TransactionFormDialog } from '@/components/finance/TransactionFormDialog';
import { TransactionRow } from '@/components/finance/TransactionRow';
import { Button, Card, EmptyState, Input, Select } from '@/components/ui';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { PageHeader } from '@/components/ui/PageHeader';
import { formatMoney } from '@/lib/money';
import { useTrans } from '@/lib/translation';
import type { SharedProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

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

interface Filters {
    search: string;
    type: string;
    category: number | null;
    account: number | null;
    from: string;
    to: string;
    min_amount: string;
    max_amount: string;
    sort: string;
}

interface Props extends SharedProps {
    transactions: { data: Tx[]; meta: Pagination };
    filters: Filters;
    accounts: { id: number; name: string; type: string; balance: string }[];
    categories: { id: number; name: string; type: string }[];
}

interface Pagination {
    total?: number;
    current_page?: number;
    last_page?: number;
    links?: ({ url: string | null; label: string; active: boolean }[] | null)[];
}

export default function TransactionsIndex() {
    const t = useTrans();
    const { app, transactions, filters, accounts, categories } =
        usePage<Props>().props;
    const currency = app.currency;

    const [search, setSearch] = useState(filters.search ?? '');
    const [showFilters, setShowFilters] = useState(false);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [f, setF] = useState<Filters>({ ...filters });
    const [deleting, setDeleting] = useState<Tx | null>(null);
    const [activeFilterCount, setActiveFilterCount] = useState(0);

    useEffect(() => {
        const count = Object.entries(filters).filter(
            ([k, v]) =>
                !['search', 'sort'].includes(k) &&
                v !== '' &&
                v !== null &&
                v !== undefined,
        ).length;
        setActiveFilterCount(count);
    }, [filters]);

    const apply = (patch: Partial<Filters>) => {
        const next = { ...f, ...patch };
        setF(next);
        const params: Record<string, string | number> = {};
        (Object.keys(next) as (keyof Filters)[]).forEach((k) => {
            const v = next[k];
            if (v !== '' && v !== null && v !== undefined) params[k] = v;
        });
        router.get('/transactions', params, {
            preserveState: true,
            replace: true,
        });
    };

    const applyRef = useRef(apply);
    applyRef.current = apply;

    useEffect(() => {
        const id = window.setTimeout(() => {
            if (search !== (filters.search ?? '')) {
                applyRef.current({ search });
            }
        }, 400);
        return () => window.clearTimeout(id);
    }, [search, filters.search]);

    const applyFilters = () => {
        setShowFilters(false);
        apply({
            search,
            from: f.from,
            to: f.to,
            type: f.type,
            category: f.category,
            account: f.account,
            min_amount: f.min_amount,
            max_amount: f.max_amount,
            sort: f.sort,
        });
    };

    const gotoPage = (url: string | null) => {
        if (url) router.visit(url, { preserveState: true });
    };

    const confirmDelete = () => {
        if (!deleting) return;
        router.delete(`/transactions/${deleting.id}`);
        setDeleting(null);
    };

    const income = transactions.data
        .filter((x) => x.type === 'income')
        .reduce((s, x) => s + Number(x.amount), 0);
    const expense = transactions.data
        .filter((x) => x.type === 'expense')
        .reduce((s, x) => s + Number(x.amount), 0);

    return (
        <AppLayout>
            <PageHeader
                title={t('transactions.title')}
                subtitle={t('transactions.subtitle')}
                action={
                    <Button onClick={() => setDialogOpen(true)}>
                        <IconPlus size={16} />
                        {t('common.add')}
                    </Button>
                }
            />

            <TransactionFormDialog
                open={dialogOpen}
                onClose={() => setDialogOpen(false)}
                accounts={accounts}
                categories={
                    categories as {
                        id: number;
                        name: string;
                        type: 'income' | 'expense';
                        icon?: string | null;
                        color?: string | null;
                    }[]
                }
            />

            {/* Quick summary */}
            <div className="mb-4 grid grid-cols-3 gap-3">
                <div className="card p-4">
                    <p className="micro">
                        {t('transactions.this_page_income')}
                    </p>
                    <p className="text-mint mt-1.5 text-lg font-semibold tabular-nums">
                        +{formatMoney(income, { currency })}
                    </p>
                </div>
                <div className="card p-4">
                    <p className="micro">
                        {t('transactions.this_page_expense')}
                    </p>
                    <p className="text-coral mt-1.5 text-lg font-semibold tabular-nums">
                        -{formatMoney(expense, { currency })}
                    </p>
                </div>
                <div className="card p-4">
                    <p className="micro">{t('transactions.total')}</p>
                    <p className="text-ink mt-1.5 text-lg font-semibold tabular-nums">
                        {transactions.meta?.total}
                    </p>
                </div>
            </div>

            {/* Search + filters */}
            <Card padding={false} className="mb-4">
                <div className="flex flex-wrap items-center gap-2 p-3">
                    <div className="relative min-w-[200px] flex-1">
                        <IconSearch
                            size={16}
                            className="text-ink-faint pointer-events-none absolute start-3 top-1/2 -translate-y-1/2"
                        />
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={t('transactions.search_placeholder')}
                            className="input ps-9"
                        />
                    </div>
                    <Button
                        variant={showFilters ? 'soft' : 'secondary'}
                        onClick={() => setShowFilters((v) => !v)}
                    >
                        <IconFilter size={16} />
                        {t('transactions.filters')}
                        {activeFilterCount > 0 ? (
                            <span className="bg-accent text-accent-contrast flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-bold">
                                {activeFilterCount}
                            </span>
                        ) : null}
                    </Button>
                </div>

                {showFilters ? (
                    <div className="border-line grid grid-cols-2 gap-3 border-t p-4 sm:grid-cols-4">
                        <Select
                            label={t('transactions.type')}
                            options={[
                                { value: '', label: t('common.all') },
                                { value: 'income', label: t('tx.income') },
                                { value: 'expense', label: t('tx.expense') },
                                { value: 'transfer', label: t('tx.transfer') },
                            ]}
                            value={f.type}
                            onChange={(e) =>
                                setF({ ...f, type: e.target.value })
                            }
                        />
                        <Select
                            label={t('transactions.category')}
                            options={[
                                { value: '', label: t('common.all') },
                                ...categories.map((c) => ({
                                    value: c.id,
                                    label: c.name,
                                })),
                            ]}
                            value={f.category ?? ''}
                            onChange={(e) =>
                                setF({
                                    ...f,
                                    category: e.target.value
                                        ? Number(e.target.value)
                                        : null,
                                })
                            }
                        />
                        <Select
                            label={t('transactions.account')}
                            options={[
                                { value: '', label: t('common.all') },
                                ...accounts.map((a) => ({
                                    value: a.id,
                                    label: a.name,
                                })),
                            ]}
                            value={f.account ?? ''}
                            onChange={(e) =>
                                setF({
                                    ...f,
                                    account: e.target.value
                                        ? Number(e.target.value)
                                        : null,
                                })
                            }
                        />
                        <Select
                            label={t('transactions.sort')}
                            options={[
                                {
                                    value: 'newest',
                                    label: t('transactions.sort_newest'),
                                },
                                {
                                    value: 'oldest',
                                    label: t('transactions.sort_oldest'),
                                },
                                {
                                    value: 'highest',
                                    label: t('transactions.sort_highest'),
                                },
                                {
                                    value: 'lowest',
                                    label: t('transactions.sort_lowest'),
                                },
                            ]}
                            value={f.sort || 'newest'}
                            onChange={(e) =>
                                setF({ ...f, sort: e.target.value })
                            }
                        />
                        <Input
                            label={t('transactions.from')}
                            type="date"
                            value={f.from}
                            onChange={(e) =>
                                setF({ ...f, from: e.target.value })
                            }
                        />
                        <Input
                            label={t('transactions.to')}
                            type="date"
                            value={f.to}
                            onChange={(e) => setF({ ...f, to: e.target.value })}
                        />
                        <Input
                            label={t('transactions.min_amount')}
                            type="number"
                            min="0"
                            value={f.min_amount}
                            onChange={(e) =>
                                setF({ ...f, min_amount: e.target.value })
                            }
                        />
                        <div className="flex items-end">
                            <Button fullWidth onClick={applyFilters}>
                                {t('transactions.apply')}
                            </Button>
                        </div>
                    </div>
                ) : null}
            </Card>

            <Card padding={false}>
                {transactions.data.length === 0 ? (
                    <EmptyState
                        title={t('transactions.empty_title')}
                        description={t('transactions.empty_hint')}
                    />
                ) : (
                    <div className="divide-line divide-y px-3">
                        {transactions.data.map((tx) => (
                            <div
                                key={tx.id}
                                className="group flex items-center"
                            >
                                <div className="flex-1">
                                    <TransactionRow
                                        tx={tx}
                                        currency={currency}
                                    />
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setDeleting(tx)}
                                    className="text-ink-faint hover:text-coral p-2 opacity-100 transition sm:opacity-0 sm:group-hover:opacity-100"
                                    aria-label={t('common.delete')}
                                >
                                    <IconTrash size={16} />
                                </button>
                            </div>
                        ))}
                    </div>
                )}

                {transactions.meta?.last_page > 1 ? (
                    <div className="border-line flex items-center justify-between border-t px-4 py-3">
                        <Button
                            variant="secondary"
                            size="sm"
                            disabled={transactions.meta.current_page <= 1}
                            onClick={() =>
                                gotoPage(
                                    transactions.meta.links?.[
                                        transactions.meta.current_page - 1
                                    ]?.url ?? null,
                                )
                            }
                        >
                            {t('common.prev')}
                        </Button>
                        <span className="text-ink-faint text-sm">
                            {transactions.meta.current_page} /{' '}
                            {transactions.meta.last_page}
                        </span>
                        <Button
                            variant="secondary"
                            size="sm"
                            disabled={
                                transactions.meta.current_page >=
                                transactions.meta.last_page
                            }
                            onClick={() =>
                                gotoPage(
                                    transactions.meta.links?.[
                                        transactions.meta.current_page + 1
                                    ]?.url ?? null,
                                )
                            }
                        >
                            {t('common.next')}
                        </Button>
                    </div>
                ) : null}
            </Card>

            <ConfirmDialog
                open={deleting !== null}
                onClose={() => setDeleting(null)}
                onConfirm={confirmDelete}
                title={t('transactions.delete_title')}
                message={t('transactions.delete_hint')}
                confirmLabel={t('common.delete')}
            />
        </AppLayout>
    );
}
