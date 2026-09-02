import AppLayout from '@/Layouts/AppLayout';
import { IconPlus } from '@/components/Icons';
import { AccountCard } from '@/components/finance/AccountCard';
import { TransactionFormDialog } from '@/components/finance/TransactionFormDialog';
import { Button, Dialog, EmptyState, Input, Select } from '@/components/ui';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { PageHeader } from '@/components/ui/PageHeader';
import { formatMoney } from '@/lib/money';
import { useTrans } from '@/lib/translation';
import type { SharedProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface AccountItem {
    id: number;
    name: string;
    type: string;
    balance: string;
    icon?: string | null;
    color?: string | null;
    description?: string | null;
    transactions_count?: number;
    currency?: string;
}

interface Props extends SharedProps {
    accounts: AccountItem[];
    totalBalance: string;
}

const ACCOUNT_TYPES = [
    { value: 'cash', label: 'accounts.type_cash' },
    { value: 'bank', label: 'accounts.type_bank' },
    { value: 'savings', label: 'accounts.type_savings' },
    { value: 'credit_card', label: 'accounts.type_credit_card' },
    { value: 'digital_wallet', label: 'accounts.type_digital_wallet' },
];

export default function AccountsIndex() {
    const t = useTrans();
    const { app, accounts, totalBalance } = usePage<Props>().props;
    const currency = app.currency;

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<AccountItem | null>(null);
    const [deleting, setDeleting] = useState<AccountItem | null>(null);
    const [addingMoneyTo, setAddingMoneyTo] = useState<AccountItem | null>(
        null,
    );
    const [form, setForm] = useState({
        name: '',
        type: 'bank',
        balance: '',
        description: '',
        institution: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    const openCreate = () => {
        setEditing(null);
        setErrors({});
        setForm({
            name: '',
            type: 'bank',
            balance: '',
            description: '',
            institution: '',
        });
        setDialogOpen(true);
    };

    const openEdit = (a: AccountItem) => {
        setEditing(a);
        setErrors({});
        setForm({
            name: a.name,
            type: a.type,
            balance: '',
            description: a.description ?? '',
            institution: '',
        });
        setDialogOpen(true);
    };

    const submit = () => {
        setSubmitting(true);
        setErrors({});
        const payload: Record<string, string> = {
            name: form.name,
            type: form.type,
            description: form.description,
        };
        if (form.balance) payload.balance = form.balance;
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setSubmitting(false);
                setDialogOpen(false);
            },
            onError: (e: Record<string, string>) => {
                setSubmitting(false);
                setErrors(e);
            },
        };
        if (!editing) {
            router.post('/accounts', payload, options);
        } else {
            router.put(`/accounts/${editing.id}`, payload, options);
        }
    };

    const confirmDelete = () => {
        if (!deleting) return;
        router.delete(`/accounts/${deleting.id}`);
        setDeleting(null);
    };

    return (
        <AppLayout>
            <PageHeader
                title={t('accounts.title')}
                subtitle={t('accounts.subtitle')}
                action={
                    <Button onClick={openCreate}>
                        <IconPlus size={16} />
                        {t('accounts.add')}
                    </Button>
                }
            />

            <div className="border-line bg-surface shadow-card mb-6 flex items-center justify-between rounded-2xl border p-5">
                <div>
                    <p className="micro">{t('accounts.total_balance')}</p>
                    <p className="text-ink mt-1 text-3xl font-semibold tracking-tight tabular-nums">
                        {formatMoney(totalBalance, { currency })}
                    </p>
                </div>
                <div className="me-1 text-end">
                    <p className="text-accent text-3xl font-semibold tabular-nums">
                        {accounts.length}
                    </p>
                    <p className="text-ink-faint text-xs font-medium">
                        {t('accounts.count')}
                    </p>
                </div>
            </div>

            {accounts.length === 0 ? (
                <EmptyState
                    title={t('accounts.empty_title')}
                    description={t('accounts.empty_hint')}
                    action={
                        <Button onClick={openCreate}>
                            <IconPlus size={16} />
                            {t('accounts.add')}
                        </Button>
                    }
                />
            ) : (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {accounts.map((a) => (
                        <div key={a.id} className="group relative">
                            <AccountCard account={a} currency={currency} />
                            <div className="absolute inset-x-3 bottom-3 flex items-center justify-between gap-1 opacity-100 transition sm:opacity-0 sm:group-hover:opacity-100">
                                <span className="bg-surface text-ink-soft rounded-lg px-2.5 py-1 text-xs font-semibold shadow-sm">
                                    {a.transactions_count ?? 0}
                                </span>
                                <div className="flex gap-1">
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        onClick={() => setAddingMoneyTo(a)}
                                    >
                                        {t('accounts.add_money')}
                                    </Button>
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        onClick={() => openEdit(a)}
                                    >
                                        {t('common.edit')}
                                    </Button>
                                    <Button
                                        variant="danger"
                                        size="sm"
                                        onClick={() => setDeleting(a)}
                                    >
                                        {t('common.delete')}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <Dialog
                open={dialogOpen}
                onClose={() => setDialogOpen(false)}
                title={editing ? t('accounts.edit') : t('accounts.add')}
                footer={
                    <div className="flex justify-end gap-2">
                        <Button
                            variant="secondary"
                            onClick={() => setDialogOpen(false)}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button
                            onClick={submit}
                            loading={submitting}
                            disabled={!form.name}
                        >
                            {editing ? t('common.save') : t('common.create')}
                        </Button>
                    </div>
                }
            >
                <div className="space-y-4">
                    <Input
                        label={t('accounts.name')}
                        value={form.name}
                        onChange={(e) =>
                            setForm({ ...form, name: e.target.value })
                        }
                        placeholder={t('accounts.name_placeholder')}
                        error={errors.name}
                    />
                    <Select
                        label={t('accounts.type')}
                        options={ACCOUNT_TYPES.map((o) => ({
                            value: o.value,
                            label: t(o.label),
                        }))}
                        value={form.type}
                        onChange={(e) =>
                            setForm({ ...form, type: e.target.value })
                        }
                    />
                    {!editing ? (
                        <Input
                            label={t('accounts.balance')}
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.balance}
                            onChange={(e) =>
                                setForm({ ...form, balance: e.target.value })
                            }
                            error={errors.balance}
                        />
                    ) : null}
                    <Input
                        label={t('accounts.description')}
                        value={form.description}
                        onChange={(e) =>
                            setForm({ ...form, description: e.target.value })
                        }
                    />
                </div>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onClose={() => setDeleting(null)}
                onConfirm={confirmDelete}
                title={t('accounts.delete_title')}
                message={t('accounts.delete_hint')}
                confirmLabel={t('common.delete')}
            />

            <TransactionFormDialog
                open={addingMoneyTo !== null}
                onClose={() => setAddingMoneyTo(null)}
                fixedType="income"
                title={t('accounts.add_money')}
                initialAccountId={addingMoneyTo?.id}
                accounts={accounts.map((a) => ({
                    id: a.id,
                    name: a.name,
                    type: a.type,
                    balance: a.balance,
                }))}
            />
        </AppLayout>
    );
}
