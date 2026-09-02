import AppLayout from '@/Layouts/AppLayout';
import {
    IconCalendar,
    IconPlus,
    IconUser,
    IconWallet,
} from '@/components/Icons';
import {
    Badge,
    Button,
    Card,
    Dialog,
    EmptyState,
    Input,
    Progress,
    Select,
} from '@/components/ui';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { PageHeader } from '@/components/ui/PageHeader';
import { formatMoney } from '@/lib/money';
import { useTrans } from '@/lib/translation';
import type { SharedProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface Debt {
    id: number;
    name: string;
    type: string;
    original_amount: string;
    remaining_amount: string;
    interest_rate: string;
    installment_amount: string;
    frequency?: string | null;
    next_payment_date?: string | null;
    due_date?: string | null;
    notes?: string | null;
    status: string;
    account?: string | null;
    monthly_payment: string;
    progress: number;
    payments_remaining: number;
}

interface Props extends SharedProps {
    debts: Debt[];
    summary: {
        total_remaining: string;
        total_original: string;
        monthly_payments: string;
        owed_to_user: string;
        progress: number;
        count: number;
    };
    accounts: { id: number; name: string }[];
}

const statusTone: Record<string, 'success' | 'warning' | 'neutral'> = {
    active: 'success',
    paid_off: 'neutral',
    paused: 'warning',
};

export default function DebtsIndex() {
    const t = useTrans();
    const { app, debts, summary, accounts } = usePage<Props>().props;
    const currency = app.currency;

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Debt | null>(null);
    const [deleting, setDeleting] = useState<Debt | null>(null);
    const [form, setForm] = useState({
        name: '',
        type: 'loan',
        original_amount: '',
        remaining_amount: '',
        interest_rate: '',
        installment_amount: '',
        frequency: 'monthly',
        next_payment_date: '',
        due_date: '',
        account_id: '',
        notes: '',
        status: 'active',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    const openCreate = () => {
        setEditing(null);
        setErrors({});
        setForm({
            name: '',
            type: 'loan',
            original_amount: '',
            remaining_amount: '',
            interest_rate: '',
            installment_amount: '',
            frequency: 'monthly',
            next_payment_date: '',
            due_date: '',
            account_id: '',
            notes: '',
            status: 'active',
        });
        setDialogOpen(true);
    };

    const openEdit = (d: Debt) => {
        setEditing(d);
        setErrors({});
        setForm({
            name: d.name,
            type: d.type,
            original_amount: Number(d.original_amount).toString(),
            remaining_amount: Number(d.remaining_amount).toString(),
            interest_rate: d.interest_rate
                ? Number(d.interest_rate).toString()
                : '',
            installment_amount: d.installment_amount
                ? Number(d.installment_amount).toString()
                : '',
            frequency: d.frequency ?? 'monthly',
            next_payment_date: d.next_payment_date ?? '',
            due_date: d.due_date ?? '',
            account_id: '',
            notes: d.notes ?? '',
            status: d.status,
        });
        setDialogOpen(true);
    };

    const submit = () => {
        setSubmitting(true);
        setErrors({});
        const payload: any = {
            name: form.name,
            type: form.type,
            original_amount: form.original_amount,
            status: form.status,
        };
        if (form.remaining_amount)
            payload.remaining_amount = form.remaining_amount;
        if (form.interest_rate) payload.interest_rate = form.interest_rate;
        if (form.installment_amount)
            payload.installment_amount = form.installment_amount;
        if (form.frequency) payload.frequency = form.frequency;
        if (form.next_payment_date)
            payload.next_payment_date = form.next_payment_date;
        if (form.due_date) payload.due_date = form.due_date;
        if (form.account_id) payload.account_id = form.account_id;
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
            router.put(`/debts/${editing.id}`, payload, options);
        } else {
            router.post('/debts', payload, options);
        }
    };

    const confirmDelete = () => {
        if (!deleting) return;
        router.delete(`/debts/${deleting.id}`);
        setDeleting(null);
    };

    const isOwedToUser = (d: Debt) => d.type === 'owed_to_user';

    const freqOptions = [
        { value: 'weekly', label: t('debts.freq_weekly') },
        { value: 'monthly', label: t('debts.freq_monthly') },
        { value: 'yearly', label: t('debts.freq_yearly') },
    ];

    const typeOptions = [
        { value: 'personal', label: t('debts.type_personal') },
        { value: 'loan', label: t('debts.type_loan') },
        { value: 'credit', label: t('debts.type_credit') },
        { value: 'owed_to_user', label: t('debts.type_owed_to_user') },
        { value: 'owed_to_others', label: t('debts.type_owed_to_others') },
    ];

    return (
        <AppLayout>
            <PageHeader
                title={t('debts.title')}
                subtitle={t('debts.subtitle')}
                action={
                    <Button onClick={openCreate}>
                        <IconPlus size={16} />
                        {t('debts.add')}
                    </Button>
                }
            />

            {/* Summary */}
            <div className="mb-5 grid grid-cols-3 gap-3">
                <Card className="p-4">
                    <p className="text-ink-faint text-xs">
                        {t('debts.total_remaining')}
                    </p>
                    <p className="text-coral mt-1 text-lg font-bold tabular-nums">
                        {formatMoney(summary.total_remaining, { currency })}
                    </p>
                </Card>
                <Card className="p-4">
                    <p className="text-ink-faint text-xs">
                        {t('debts.monthly_payments')}
                    </p>
                    <p className="text-ink mt-1 text-lg font-bold tabular-nums">
                        {formatMoney(summary.monthly_payments, { currency })}
                    </p>
                </Card>
                <Card className="p-4">
                    <p className="text-ink-faint text-xs">
                        {t('debts.owed_to_user')}
                    </p>
                    <p className="text-mint mt-1 text-lg font-bold tabular-nums">
                        {formatMoney(summary.owed_to_user, { currency })}
                    </p>
                </Card>
            </div>

            {debts.length === 0 ? (
                <EmptyState
                    icon={<IconWallet size={28} />}
                    title={t('debts.empty_title')}
                    description={t('debts.empty_hint')}
                    action={
                        <Button onClick={openCreate}>
                            <IconPlus size={16} />
                            {t('debts.add')}
                        </Button>
                    }
                />
            ) : (
                <Card padding={false}>
                    <div className="divide-line divide-y dark:divide-white/5">
                        {debts.map((d) => (
                            <div
                                key={d.id}
                                className="group flex flex-wrap items-center gap-3 px-4 py-3"
                            >
                                <span
                                    className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${isOwedToUser(d) ? 'bg-mint/10 text-mint' : 'bg-coral/10 text-coral'}`}
                                >
                                    {isOwedToUser(d) ? (
                                        <IconUser size={18} />
                                    ) : (
                                        <IconWallet size={18} />
                                    )}
                                </span>
                                <div className="min-w-0 flex-1 basis-40">
                                    <p className="text-ink flex items-center gap-2 truncate text-sm font-medium dark:text-white">
                                        {d.name}
                                        <Badge
                                            tone={
                                                isOwedToUser(d)
                                                    ? 'success'
                                                    : 'brand'
                                            }
                                        >
                                            {isOwedToUser(d)
                                                ? t('debts.owed_to_you')
                                                : t('debts.you_owe')}
                                        </Badge>
                                        {d.status !== 'active' ? (
                                            <Badge tone={statusTone[d.status]}>
                                                {t(`debts.status_${d.status}`)}
                                            </Badge>
                                        ) : null}
                                    </p>
                                    <p className="text-ink-faint flex items-center gap-1 truncate text-xs">
                                        <IconCalendar size={12} />
                                        {d.next_payment_date
                                            ? `${t('debts.next_payment')}: ${d.next_payment_date}`
                                            : d.frequency
                                              ? t(`debts.freq_${d.frequency}`)
                                              : t('debts.no_payment_date')}
                                    </p>
                                </div>
                                <div className="flex shrink-0 items-center gap-2">
                                    <div className="text-end">
                                        <p className="text-coral text-sm font-semibold tabular-nums">
                                            {formatMoney(d.remaining_amount, {
                                                currency,
                                            })}
                                        </p>
                                        <p className="text-ink-faint text-xs">
                                            {formatMoney(d.monthly_payment, {
                                                currency,
                                            })}
                                            {t('debts.monthly')}
                                        </p>
                                    </div>
                                    <div className="flex gap-1 opacity-100 transition sm:opacity-0 sm:group-hover:opacity-100">
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            onClick={() => openEdit(d)}
                                        >
                                            {t('common.edit')}
                                        </Button>
                                        <Button
                                            variant="danger"
                                            size="sm"
                                            onClick={() => setDeleting(d)}
                                        >
                                            {t('common.delete')}
                                        </Button>
                                    </div>
                                </div>
                                {!isOwedToUser(d) ? (
                                    <div className="basis-full">
                                        <div className="flex items-center justify-between text-xs">
                                            <span className="text-ink-faint">
                                                {t('debts.progress')}
                                            </span>
                                            <span className="text-ink-faint tabular-nums">
                                                {d.progress}%
                                            </span>
                                        </div>
                                        <Progress
                                            value={d.progress}
                                            tone="brand"
                                        />
                                    </div>
                                ) : null}
                            </div>
                        ))}
                    </div>
                </Card>
            )}

            <Dialog
                open={dialogOpen}
                onClose={() => setDialogOpen(false)}
                title={editing ? t('debts.edit') : t('debts.add')}
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
                            disabled={!form.name || !form.original_amount}
                        >
                            {editing ? t('common.save') : t('common.create')}
                        </Button>
                    </div>
                }
            >
                <div className="space-y-4">
                    <Input
                        label={t('debts.name')}
                        value={form.name}
                        onChange={(e) =>
                            setForm({ ...form, name: e.target.value })
                        }
                        placeholder={t('debts.name_placeholder')}
                        error={errors.name}
                    />
                    <div className="grid grid-cols-2 gap-3">
                        <Select
                            label={t('debts.type')}
                            options={typeOptions}
                            value={form.type}
                            onChange={(e) =>
                                setForm({ ...form, type: e.target.value })
                            }
                            error={errors.type}
                        />
                        <Select
                            label={t('debts.frequency')}
                            options={freqOptions}
                            value={form.frequency}
                            onChange={(e) =>
                                setForm({ ...form, frequency: e.target.value })
                            }
                            error={errors.frequency}
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <Input
                            label={t('debts.original_amount')}
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.original_amount}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    original_amount: e.target.value,
                                })
                            }
                            error={errors.original_amount}
                        />
                        <Input
                            label={t('debts.remaining_amount')}
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.remaining_amount}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    remaining_amount: e.target.value,
                                })
                            }
                            error={errors.remaining_amount}
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <Input
                            label={t('debts.interest_rate')}
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            value={form.interest_rate}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    interest_rate: e.target.value,
                                })
                            }
                            error={errors.interest_rate}
                        />
                        <Input
                            label={t('debts.installment_amount')}
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.installment_amount}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    installment_amount: e.target.value,
                                })
                            }
                            error={errors.installment_amount}
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <Input
                            label={t('debts.next_payment')}
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
                        <Input
                            label={t('debts.due_date')}
                            type="date"
                            value={form.due_date}
                            onChange={(e) =>
                                setForm({ ...form, due_date: e.target.value })
                            }
                            error={errors.due_date}
                        />
                    </div>
                    <Select
                        label={t('debts.account')}
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
                        label={t('debts.status')}
                        options={[
                            {
                                value: 'active',
                                label: t('debts.status_active'),
                            },
                            {
                                value: 'paid_off',
                                label: t('debts.status_paid_off'),
                            },
                            {
                                value: 'paused',
                                label: t('debts.status_paused'),
                            },
                        ]}
                        value={form.status}
                        onChange={(e) =>
                            setForm({ ...form, status: e.target.value })
                        }
                        error={errors.status}
                    />
                    <Input
                        label={t('debts.notes')}
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
                title={t('debts.delete_title')}
                message={t('debts.delete_hint')}
                confirmLabel={t('common.delete')}
            />
        </AppLayout>
    );
}
