import { todayLocal } from '@/lib/date';
import { useTrans } from '@/lib/translation';
import type { AccountOption, CategoryOption, SharedProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { IconArrowDownRight, IconArrowUpRight, IconTransfer } from '../Icons';
import { Button, Dialog, Input, Select } from '../ui';

interface Props {
    open: boolean;
    onClose: () => void;
    initialType?: 'income' | 'expense' | 'transfer';
    accounts?: AccountOption[];
    categories?: CategoryOption[];
    title?: string;
    /** Pre-select this account (used by "Add money" and budget "Spend"). */
    initialAccountId?: number | string;
    /** Lock the category to this id (used by category-scope budget "Spend"). */
    lockedCategoryId?: number | string;
    /** Hide the type tabs and lock the transaction type. */
    fixedType?: 'income' | 'expense';
}

type Type = 'income' | 'expense' | 'transfer';

export function TransactionFormDialog({
    open,
    onClose,
    initialType = 'expense',
    accounts: propAccounts,
    categories: propCategories,
    title,
    initialAccountId,
    lockedCategoryId,
    fixedType,
}: Props) {
    const t = useTrans();
    const { app } = usePage<SharedProps>().props;
    const currency = app.currency;

    const [type, setType] = useState<Type>(fixedType ?? initialType);
    const [accounts, setAccounts] = useState<AccountOption[]>(
        propAccounts ?? [],
    );
    const [categories, setCategories] = useState<CategoryOption[]>(
        propCategories ?? [],
    );
    const [form, setForm] = useState<Record<string, string>>({});
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        if (open) {
            setType(fixedType ?? initialType);
            setForm({
                ...(initialAccountId != null
                    ? { account_id: String(initialAccountId) }
                    : {}),
                ...(lockedCategoryId != null
                    ? { category_id: String(lockedCategoryId) }
                    : {}),
            });
            setErrors({});
            if (
                (propAccounts?.length ?? 0) === 0 ||
                (propCategories?.length ?? 0) === 0
            ) {
                fetch('/transactions/form-data')
                    .then((r) => r.json())
                    .then((data) => {
                        if (data.accounts) setAccounts(data.accounts);
                        if (data.categories) setCategories(data.categories);
                    })
                    .catch(() => {});
            }
        }
    }, [
        open,
        initialType,
        propAccounts,
        propCategories,
        initialAccountId,
        lockedCategoryId,
        fixedType,
    ]);

    const set = (key: string, value: string) =>
        setForm((f) => ({ ...f, [key]: value }));

    const incomeCategories = categories.filter((c) => c.type === 'income');
    const expenseCategories = categories.filter((c) => c.type === 'expense');

    const categoryOptions =
        type === 'income'
            ? incomeCategories.map((c) => ({ value: c.id, label: c.name }))
            : expenseCategories.map((c) => ({ value: c.id, label: c.name }));

    const submit = () => {
        setSubmitting(true);
        setErrors({});
        const payload: any = {
            type,
            account_id: form.account_id,
            amount: form.amount,
            date: form.date || todayLocal(),
            description: form.description || undefined,
            ...(type === 'transfer'
                ? { destination_account_id: form.destination_account_id }
                : { category_id: form.category_id || undefined }),
        };

        router.post('/transactions', payload, {
            preserveScroll: true,
            onSuccess: () => {
                setSubmitting(false);
                onClose();
            },
            onError: (e) => {
                setSubmitting(false);
                setErrors(e);
            },
        });
    };

    const typeTabs: { key: Type; label: string; icon: any }[] = [
        { key: 'expense', label: t('tx.expense'), icon: IconArrowUpRight },
        { key: 'income', label: t('tx.income'), icon: IconArrowDownRight },
        { key: 'transfer', label: t('tx.transfer'), icon: IconTransfer },
    ];

    return (
        <Dialog
            open={open}
            onClose={onClose}
            title={title ?? t('tx.add')}
            size="sm"
        >
            {/* Type tabs */}
            {fixedType ? null : (
                <div
                    className="bg-surface-strong grid grid-cols-3 gap-1 rounded-xl p-1"
                    role="tablist"
                    aria-label={t('tx.type')}
                >
                    {typeTabs.map((tab) => (
                        <button
                            key={tab.key}
                            role="tab"
                            aria-selected={type === tab.key}
                            onClick={() => setType(tab.key)}
                            className={`flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition ${
                                type === tab.key
                                    ? 'bg-surface text-ink shadow-sm'
                                    : 'text-ink-faint'
                            }`}
                        >
                            <tab.icon size={16} />
                            {tab.label}
                        </button>
                    ))}
                </div>
            )}

            <div className="mt-5 space-y-4">
                <div>
                    <label className="label">{t('tx.amount')}</label>
                    <div className="relative">
                        <input
                            type="number"
                            inputMode="decimal"
                            step="0.01"
                            min="0"
                            className="input text-lg font-semibold"
                            placeholder="0.00"
                            value={form.amount ?? ''}
                            onChange={(e) => set('amount', e.target.value)}
                            aria-invalid={errors.amount ? true : undefined}
                            autoFocus
                        />
                        <span className="text-ink-faint absolute end-3 top-1/2 -translate-y-1/2 text-sm">
                            {currency}
                        </span>
                    </div>
                    {errors.amount ? (
                        <p className="mt-1 text-xs text-red-600">
                            {errors.amount}
                        </p>
                    ) : null}
                </div>

                <Select
                    label={
                        type === 'transfer'
                            ? t('tx.from_account')
                            : t('tx.account')
                    }
                    value={form.account_id ?? ''}
                    onChange={(e) => set('account_id', e.target.value)}
                    options={accounts.map((a) => ({
                        value: a.id,
                        label: a.name,
                    }))}
                    placeholder={t('common.select')}
                    error={errors.account_id}
                />

                {type === 'transfer' ? (
                    <Select
                        label={t('tx.to_account')}
                        value={form.destination_account_id ?? ''}
                        onChange={(e) =>
                            set('destination_account_id', e.target.value)
                        }
                        options={accounts
                            .filter(
                                (a) => String(a.id) !== String(form.account_id),
                            )
                            .map((a) => ({ value: a.id, label: a.name }))}
                        placeholder={t('common.select')}
                        error={errors.destination_account_id}
                    />
                ) : (
                    <Select
                        label={t('tx.category')}
                        value={form.category_id ?? ''}
                        onChange={(e) => set('category_id', e.target.value)}
                        options={categoryOptions}
                        placeholder={t('common.optional')}
                        disabled={lockedCategoryId != null}
                    />
                )}

                <Input
                    label={t('tx.date')}
                    type="date"
                    value={form.date ?? todayLocal()}
                    onChange={(e) => set('date', e.target.value)}
                />
                <Input
                    label={t('tx.description')}
                    value={form.description ?? ''}
                    onChange={(e) => set('description', e.target.value)}
                    placeholder={t('tx.description_placeholder')}
                />

                <Button
                    className="w-full"
                    loading={submitting}
                    onClick={submit}
                >
                    {t('tx.save')}
                </Button>
            </div>
        </Dialog>
    );
}
