import AppLayout from '@/Layouts/AppLayout';
import { IconPlus, IconRepeat } from '@/components/Icons';
import {
    Badge,
    Button,
    Card,
    Dialog,
    EmptyState,
    Input,
    Select,
} from '@/components/ui';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { PageHeader } from '@/components/ui/PageHeader';
import { formatMoney, formatSigned } from '@/lib/money';
import { useTrans } from '@/lib/translation';
import type { SharedProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface Recurring {
    id: number;
    name: string;
    type: string;
    amount: string;
    frequency: string;
    next_occurrence: string;
    start_date?: string;
    end_date?: string | null;
    is_active: boolean;
    account?: string | null;
    category?: string | null;
    category_icon?: string | null;
}

interface Props extends SharedProps {
    recurring: Recurring[];
    monthlyIncome: string;
    monthlyExpense: string;
    accounts: { id: number; name: string }[];
    categories: {
        id: number;
        name: string;
        type: string;
        icon?: string | null;
    }[];
}

export default function RecurringIndex() {
    const t = useTrans();
    const {
        app,
        recurring,
        monthlyIncome,
        monthlyExpense,
        accounts,
        categories,
    } = usePage<Props>().props;
    const currency = app.currency;

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Recurring | null>(null);
    const [deleting, setDeleting] = useState<Recurring | null>(null);
    const [form, setForm] = useState({
        name: '',
        type: 'expense',
        amount: '',
        account_id: '',
        category_id: '',
        frequency: 'monthly',
        start_date: '',
        end_date: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    const openCreate = () => {
        setEditing(null);
        setErrors({});
        setForm({
            name: '',
            type: 'expense',
            amount: '',
            account_id: '',
            category_id: '',
            frequency: 'monthly',
            start_date: new Date().toISOString().slice(0, 10),
            end_date: '',
        });
        setDialogOpen(true);
    };

    const openEdit = (r: Recurring) => {
        setEditing(r);
        setErrors({});
        setForm({
            name: r.name,
            type: r.type,
            amount: Number(r.amount).toString(),
            account_id: '',
            category_id: '',
            frequency: r.frequency,
            start_date: r.start_date ?? '',
            end_date: r.end_date ?? '',
        });
        setDialogOpen(true);
    };

    const submit = () => {
        setSubmitting(true);
        setErrors({});
        const payload: any = {
            name: form.name,
            type: form.type,
            amount: form.amount,
            frequency: form.frequency,
            start_date: form.start_date,
        };
        if (form.account_id) payload.account_id = form.account_id;
        if (form.category_id) payload.category_id = form.category_id;
        if (form.end_date) payload.end_date = form.end_date;
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
        if (editing) {
            router.put(`/recurring/${editing.id}`, payload, options);
        } else {
            router.post('/recurring', payload, options);
        }
    };

    const confirmDelete = () => {
        if (!deleting) return;
        router.delete(`/recurring/${deleting.id}`);
        setDeleting(null);
    };

    return (
        <AppLayout>
            <PageHeader
                title={t('recurring.title')}
                subtitle={t('recurring.subtitle')}
                action={
                    <Button onClick={openCreate}>
                        <IconPlus size={16} />
                        {t('recurring.add')}
                    </Button>
                }
            />

            {/* Monthly impact */}
            <div className="mb-5 grid grid-cols-2 gap-3">
                <Card className="p-4">
                    <p className="text-ink-faint text-xs">
                        {t('recurring.monthly_income')}
                    </p>
                    <p className="text-mint mt-1 text-lg font-bold">
                        +{formatMoney(monthlyIncome, { currency })}
                    </p>
                </Card>
                <Card className="p-4">
                    <p className="text-ink-faint text-xs">
                        {t('recurring.monthly_expense')}
                    </p>
                    <p className="text-coral mt-1 text-lg font-bold">
                        -{formatMoney(monthlyExpense, { currency })}
                    </p>
                </Card>
            </div>

            {recurring.length === 0 ? (
                <EmptyState
                    icon={<IconRepeat size={28} />}
                    title={t('recurring.empty_title')}
                    description={t('recurring.empty_hint')}
                    action={
                        <Button onClick={openCreate}>
                            <IconPlus size={16} />
                            {t('recurring.add')}
                        </Button>
                    }
                />
            ) : (
                <Card padding={false}>
                    <div className="divide-line divide-y dark:divide-white/5">
                        {recurring.map((r) => (
                            <div
                                key={r.id}
                                className="group flex items-center gap-3 px-4 py-3"
                            >
                                <span
                                    className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${r.type === 'expense' ? 'bg-coral/12 text-coral' : 'bg-mint/12 text-mint'}`}
                                >
                                    <IconRepeat size={18} />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="text-ink flex items-center gap-2 truncate text-sm font-medium dark:text-white">
                                        {r.name}
                                        {!r.is_active ? (
                                            <Badge tone="neutral">
                                                {t('common.inactive')}
                                            </Badge>
                                        ) : null}
                                    </p>
                                    <p className="text-ink-faint truncate text-xs">
                                        {t(`recurring.freq_${r.frequency}`)} ·{' '}
                                        {t('recurring.next')}:{' '}
                                        {r.next_occurrence}
                                        {r.account ? ` · ${r.account}` : ''}
                                    </p>
                                </div>
                                <span
                                    className={`shrink-0 text-sm font-semibold ${r.type === 'expense' ? 'text-ink dark:text-white' : 'text-mint'}`}
                                >
                                    {formatSigned(
                                        r.amount,
                                        r.type as any,
                                        currency,
                                    )}
                                </span>
                                <div className="flex shrink-0 gap-1 opacity-100 transition sm:opacity-0 sm:group-hover:opacity-100">
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        onClick={() => openEdit(r)}
                                    >
                                        {t('common.edit')}
                                    </Button>
                                    <Button
                                        variant="danger"
                                        size="sm"
                                        onClick={() => setDeleting(r)}
                                    >
                                        {t('common.delete')}
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                </Card>
            )}

            <Dialog
                open={dialogOpen}
                onClose={() => setDialogOpen(false)}
                title={editing ? t('recurring.edit') : t('recurring.add')}
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
                            disabled={!form.name || !form.amount}
                        >
                            {editing ? t('common.save') : t('common.create')}
                        </Button>
                    </div>
                }
            >
                <div className="space-y-4">
                    <Input
                        label={t('recurring.name')}
                        value={form.name}
                        onChange={(e) =>
                            setForm({ ...form, name: e.target.value })
                        }
                        placeholder={t('recurring.name_placeholder')}
                        error={errors.name}
                    />
                    <div className="grid grid-cols-2 gap-3">
                        <Select
                            label={t('recurring.type')}
                            options={[
                                { value: 'expense', label: t('tx.expense') },
                                { value: 'income', label: t('tx.income') },
                            ]}
                            value={form.type}
                            onChange={(e) =>
                                setForm({ ...form, type: e.target.value })
                            }
                            error={errors.type}
                        />
                        <Input
                            label={t('recurring.amount')}
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.amount}
                            onChange={(e) =>
                                setForm({ ...form, amount: e.target.value })
                            }
                            error={errors.amount}
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <Select
                            label={t('recurring.frequency')}
                            options={[
                                {
                                    value: 'daily',
                                    label: t('recurring.freq_daily'),
                                },
                                {
                                    value: 'weekly',
                                    label: t('recurring.freq_weekly'),
                                },
                                {
                                    value: 'monthly',
                                    label: t('recurring.freq_monthly'),
                                },
                                {
                                    value: 'yearly',
                                    label: t('recurring.freq_yearly'),
                                },
                            ]}
                            value={form.frequency}
                            onChange={(e) =>
                                setForm({ ...form, frequency: e.target.value })
                            }
                            error={errors.frequency}
                        />
                        <Select
                            label={t('recurring.account')}
                            options={accounts.map((a) => ({
                                value: a.id,
                                label: a.name,
                            }))}
                            value={form.account_id}
                            onChange={(e) =>
                                setForm({ ...form, account_id: e.target.value })
                            }
                            error={errors.account_id}
                        />
                    </div>
                    <Select
                        label={t('recurring.category')}
                        options={categories
                            .filter((c) => !form.type || c.type === form.type)
                            .map((c) => ({ value: c.id, label: c.name }))}
                        value={form.category_id}
                        onChange={(e) =>
                            setForm({ ...form, category_id: e.target.value })
                        }
                        error={errors.category_id}
                    />
                    <div className="grid grid-cols-2 gap-3">
                        <Input
                            label={t('recurring.start_date')}
                            type="date"
                            value={form.start_date}
                            onChange={(e) =>
                                setForm({ ...form, start_date: e.target.value })
                            }
                            error={errors.start_date}
                        />
                        <Input
                            label={t('recurring.end_date')}
                            type="date"
                            value={form.end_date}
                            onChange={(e) =>
                                setForm({ ...form, end_date: e.target.value })
                            }
                            error={errors.end_date}
                        />
                    </div>
                </div>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onClose={() => setDeleting(null)}
                onConfirm={confirmDelete}
                title={t('recurring.delete_title')}
                message={t('recurring.delete_hint')}
                confirmLabel={t('common.delete')}
            />
        </AppLayout>
    );
}
