import AppLayout from '@/Layouts/AppLayout';
import { IconCalendar, IconPlus, IconRepeat } from '@/components/Icons';
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
import { formatMoney } from '@/lib/money';
import { useTrans } from '@/lib/translation';
import type { SharedProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface Bill {
    id: number;
    name: string;
    amount: string;
    currency: string;
    frequency: string;
    next_payment_date: string;
    start_date?: string;
    end_date?: string | null;
    status: string;
    notes?: string | null;
    category?: string | null;
    category_icon?: string | null;
    account?: string | null;
    monthly_amount: string;
}

interface Props extends SharedProps {
    bills: Bill[];
    monthlyCost: string;
    yearlyCost: string;
    activeCount: number;
    upcoming: {
        date: string;
        date_human: string;
        bill: { id: number; name: string; amount: string; currency: string };
    }[];
    accounts: { id: number; name: string }[];
    categories: {
        id: number;
        name: string;
        type: string;
        icon?: string | null;
    }[];
}

const statusTone: Record<string, 'success' | 'warning' | 'danger' | 'neutral'> =
    {
        active: 'success',
        paused: 'warning',
        cancelled: 'danger',
    };

export default function BillsIndex() {
    const t = useTrans();
    const {
        app,
        bills,
        monthlyCost,
        yearlyCost,
        activeCount,
        accounts,
        categories,
    } = usePage<Props>().props;
    const currency = app.currency;

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Bill | null>(null);
    const [deleting, setDeleting] = useState<Bill | null>(null);
    const [form, setForm] = useState({
        name: '',
        amount: '',
        frequency: 'monthly',
        interval: '1',
        next_payment_date: '',
        start_date: '',
        end_date: '',
        category_id: '',
        account_id: '',
        status: 'active',
        notes: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    const openCreate = () => {
        setEditing(null);
        setErrors({});
        setForm({
            name: '',
            amount: '',
            frequency: 'monthly',
            interval: '1',
            next_payment_date: new Date().toISOString().slice(0, 10),
            start_date: '',
            end_date: '',
            category_id: '',
            account_id: '',
            status: 'active',
            notes: '',
        });
        setDialogOpen(true);
    };

    const openEdit = (b: Bill) => {
        setEditing(b);
        setErrors({});
        setForm({
            name: b.name,
            amount: Number(b.amount).toString(),
            frequency: b.frequency,
            interval: '1',
            next_payment_date: b.next_payment_date,
            start_date: b.start_date ?? '',
            end_date: b.end_date ?? '',
            category_id: '',
            account_id: '',
            status: b.status,
            notes: b.notes ?? '',
        });
        setDialogOpen(true);
    };

    const submit = () => {
        setSubmitting(true);
        setErrors({});
        const payload: any = {
            name: form.name,
            amount: form.amount,
            frequency: form.frequency,
            interval: form.interval,
            next_payment_date: form.next_payment_date,
            status: form.status,
        };
        if (form.category_id) payload.category_id = form.category_id;
        if (form.account_id) payload.account_id = form.account_id;
        if (form.start_date) payload.start_date = form.start_date;
        if (form.end_date) payload.end_date = form.end_date;
        if (form.notes) payload.notes = form.notes;
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
            router.put(`/bills/${editing.id}`, payload, options);
        } else {
            router.post('/bills', payload, options);
        }
    };

    const confirmDelete = () => {
        if (!deleting) return;
        router.delete(`/bills/${deleting.id}`);
        setDeleting(null);
    };

    const freqOptions = [
        { value: 'daily', label: t('bills.freq_daily') },
        { value: 'weekly', label: t('bills.freq_weekly') },
        { value: 'monthly', label: t('bills.freq_monthly') },
        { value: 'yearly', label: t('bills.freq_yearly') },
    ];

    return (
        <AppLayout>
            <PageHeader
                title={t('bills.title')}
                subtitle={t('bills.subtitle')}
                action={
                    <Button onClick={openCreate}>
                        <IconPlus size={16} />
                        {t('bills.add')}
                    </Button>
                }
            />

            {/* Summary */}
            <div className="mb-5 grid grid-cols-3 gap-3">
                <Card className="p-4">
                    <p className="text-ink-faint text-xs">
                        {t('bills.monthly_cost')}
                    </p>
                    <p className="text-ink mt-1 text-lg font-bold tabular-nums">
                        {formatMoney(monthlyCost, { currency })}
                    </p>
                </Card>
                <Card className="p-4">
                    <p className="text-ink-faint text-xs">
                        {t('bills.yearly_cost')}
                    </p>
                    <p className="text-ink mt-1 text-lg font-bold tabular-nums">
                        {formatMoney(yearlyCost, { currency })}
                    </p>
                </Card>
                <Card className="p-4">
                    <p className="text-ink-faint text-xs">
                        {t('bills.active')}
                    </p>
                    <p className="text-accent mt-1 text-lg font-bold tabular-nums">
                        {activeCount}
                    </p>
                </Card>
            </div>

            {bills.length === 0 ? (
                <EmptyState
                    icon={<IconRepeat size={28} />}
                    title={t('bills.empty_title')}
                    description={t('bills.empty_hint')}
                    action={
                        <Button onClick={openCreate}>
                            <IconPlus size={16} />
                            {t('bills.add')}
                        </Button>
                    }
                />
            ) : (
                <Card padding={false}>
                    <div className="divide-line divide-y dark:divide-white/5">
                        {bills.map((b) => (
                            <div
                                key={b.id}
                                className="group flex flex-wrap items-center gap-3 px-4 py-3"
                            >
                                <span
                                    className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${b.status === 'cancelled' ? 'bg-coral/10 text-coral' : 'bg-accent-soft text-accent'}`}
                                >
                                    <IconRepeat size={18} />
                                </span>
                                <div className="min-w-0 flex-1 basis-40">
                                    <p className="text-ink flex items-center gap-2 truncate text-sm font-medium dark:text-white">
                                        {b.name}
                                        {b.status !== 'active' ? (
                                            <Badge tone={statusTone[b.status]}>
                                                {t(`bills.status_${b.status}`)}
                                            </Badge>
                                        ) : null}
                                    </p>
                                    <p className="text-ink-faint flex items-center gap-1 truncate text-xs">
                                        <IconCalendar size={12} />
                                        {t('bills.next_payment')}:{' '}
                                        {b.next_payment_date}
                                        {b.account ? ` · ${b.account}` : ''}
                                    </p>
                                </div>
                                <div className="flex shrink-0 items-center gap-2">
                                    <div className="text-end">
                                        <p className="text-ink text-sm font-semibold tabular-nums">
                                            {formatMoney(b.amount, {
                                                currency: b.currency,
                                            })}
                                        </p>
                                        <p className="text-ink-faint text-xs">
                                            {t(`bills.freq_${b.frequency}`)} ·{' '}
                                            {formatMoney(b.monthly_amount, {
                                                currency,
                                            })}
                                            {t('debts.monthly')}
                                        </p>
                                    </div>
                                    <div className="flex gap-1 opacity-100 transition sm:opacity-0 sm:group-hover:opacity-100">
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            onClick={() => openEdit(b)}
                                        >
                                            {t('common.edit')}
                                        </Button>
                                        <Button
                                            variant="danger"
                                            size="sm"
                                            onClick={() => setDeleting(b)}
                                        >
                                            {t('common.delete')}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </Card>
            )}

            <Dialog
                open={dialogOpen}
                onClose={() => setDialogOpen(false)}
                title={editing ? t('bills.edit') : t('bills.add')}
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
                        label={t('bills.name')}
                        value={form.name}
                        onChange={(e) =>
                            setForm({ ...form, name: e.target.value })
                        }
                        placeholder={t('bills.name_placeholder')}
                        error={errors.name}
                    />
                    <div className="grid grid-cols-2 gap-3">
                        <Input
                            label={t('bills.amount')}
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.amount}
                            onChange={(e) =>
                                setForm({ ...form, amount: e.target.value })
                            }
                            error={errors.amount}
                        />
                        <Select
                            label={t('bills.frequency')}
                            options={freqOptions}
                            value={form.frequency}
                            onChange={(e) =>
                                setForm({ ...form, frequency: e.target.value })
                            }
                            error={errors.frequency}
                        />
                    </div>
                    <Input
                        label={t('bills.next_payment')}
                        type="date"
                        value={form.next_payment_date}
                        onChange={(e) =>
                            setForm({
                                ...form,
                                next_payment_date: e.target.value,
                            })
                        }
                        error={errors.next_payment_date}
                    />
                    <div className="grid grid-cols-2 gap-3">
                        <Select
                            label={t('bills.account')}
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
                        <Select
                            label={t('bills.category')}
                            options={categories.map((c) => ({
                                value: c.id,
                                label: c.name,
                            }))}
                            value={form.category_id}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    category_id: e.target.value,
                                })
                            }
                            error={errors.category_id}
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <Input
                            label={t('bills.start_date')}
                            type="date"
                            value={form.start_date}
                            onChange={(e) =>
                                setForm({ ...form, start_date: e.target.value })
                            }
                            error={errors.start_date}
                        />
                        <Input
                            label={t('bills.end_date')}
                            type="date"
                            value={form.end_date}
                            onChange={(e) =>
                                setForm({ ...form, end_date: e.target.value })
                            }
                            error={errors.end_date}
                        />
                    </div>
                    <Select
                        label={t('bills.status')}
                        options={[
                            {
                                value: 'active',
                                label: t('bills.status_active'),
                            },
                            {
                                value: 'paused',
                                label: t('bills.status_paused'),
                            },
                            {
                                value: 'cancelled',
                                label: t('bills.status_cancelled'),
                            },
                        ]}
                        value={form.status}
                        onChange={(e) =>
                            setForm({ ...form, status: e.target.value })
                        }
                        error={errors.status}
                    />
                    <Input
                        label={t('bills.notes')}
                        value={form.notes}
                        onChange={(e) =>
                            setForm({ ...form, notes: e.target.value })
                        }
                        error={errors.notes}
                    />
                </div>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onClose={() => setDeleting(null)}
                onConfirm={confirmDelete}
                title={t('bills.delete_title')}
                message={t('bills.delete_hint')}
                confirmLabel={t('common.delete')}
            />
        </AppLayout>
    );
}
