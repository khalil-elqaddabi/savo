import AppLayout from '@/Layouts/AppLayout';
import { IconPlus, IconSparkle } from '@/components/Icons';
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

interface Goal {
    id: number;
    name: string;
    target: string;
    saved: string;
    remaining: string;
    progress_percent: number;
    days_remaining?: number | null;
    required_monthly?: string | null;
    required_weekly?: string | null;
    required_daily?: string | null;
    on_track?: boolean | null;
    icon?: string | null;
    color?: string | null;
    description?: string | null;
    deadline?: string | null;
    is_completed: boolean;
    account_id?: number | null;
}

interface AccountOption {
    id: number;
    name: string;
    type: string;
    balance: string;
}

interface Props extends SharedProps {
    goals: Goal[];
    accounts: AccountOption[];
    totalTarget: string;
    totalSaved: string;
}

const hueFor: Record<string, string> = {
    travel: '#10b981',
    emergency: '#f59e0b',
    car: '#0ea5e9',
    house: '#8b5cf6',
};

export default function GoalsIndex() {
    const t = useTrans();
    const { app, goals, accounts, totalTarget, totalSaved } =
        usePage<Props>().props;
    const currency = app.currency;

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Goal | null>(null);
    const [deleting, setDeleting] = useState<Goal | null>(null);
    const [contributing, setContributing] = useState<Goal | null>(null);
    const [contributeAmount, setContributeAmount] = useState('');
    const [contributeAccountId, setContributeAccountId] = useState('');
    const [form, setForm] = useState({
        name: '',
        target_amount: '',
        deadline: '',
        description: '',
        icon: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    const openCreate = () => {
        setEditing(null);
        setErrors({});
        setForm({
            name: '',
            target_amount: '',
            deadline: '',
            description: '',
            icon: '',
        });
        setDialogOpen(true);
    };

    const openEdit = (g: Goal) => {
        setEditing(g);
        setErrors({});
        setForm({
            name: g.name,
            target_amount: Number(g.target).toString(),
            deadline: g.deadline ?? '',
            description: g.description ?? '',
            icon: g.icon ?? '',
        });
        setDialogOpen(true);
    };

    const submit = () => {
        setSubmitting(true);
        setErrors({});
        const payload: any = {
            name: form.name,
            target_amount: form.target_amount,
            description: form.description,
        };
        if (form.deadline) payload.deadline = form.deadline;
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
            router.put(`/goals/${editing.id}`, payload, options);
        } else {
            router.post('/goals', payload, options);
        }
    };

    const confirmDelete = () => {
        if (!deleting) return;
        router.delete(`/goals/${deleting.id}`);
        setDeleting(null);
    };

    const submitContribution = () => {
        if (!contributing || !contributeAmount || !contributeAccountId) return;
        router.post(`/goals/${contributing.id}/contribute`, {
            amount: contributeAmount,
            account_id: contributeAccountId,
        });
        setContributing(null);
        setContributeAmount('');
        setContributeAccountId('');
    };

    return (
        <AppLayout>
            <PageHeader
                title={t('goals.title')}
                subtitle={t('goals.subtitle')}
                action={
                    <Button onClick={openCreate}>
                        <IconPlus size={16} />
                        {t('goals.add')}
                    </Button>
                }
            />

            {goals.length === 0 ? (
                <EmptyState
                    icon={<IconSparkle size={28} />}
                    title={t('goals.empty_title')}
                    description={t('goals.empty_hint')}
                    action={
                        <Button onClick={openCreate}>
                            <IconPlus size={16} />
                            {t('goals.add')}
                        </Button>
                    }
                />
            ) : (
                <>
                    <div className="mb-5 grid grid-cols-2 gap-3">
                        <Card className="p-4">
                            <p className="text-ink-faint text-xs">
                                {t('goals.total_target')}
                            </p>
                            <p className="text-ink mt-1 text-lg font-bold dark:text-white">
                                {formatMoney(totalTarget, { currency })}
                            </p>
                        </Card>
                        <Card className="p-4">
                            <p className="text-ink-faint text-xs">
                                {t('goals.total_saved')}
                            </p>
                            <p className="text-mint mt-1 text-lg font-bold">
                                {formatMoney(totalSaved, { currency })}
                            </p>
                        </Card>
                    </div>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        {goals.map((g) => {
                            const hue =
                                g.color ?? hueFor[g.icon ?? ''] ?? '#10b981';
                            return (
                                <Card key={g.id} className="group">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="flex items-center gap-3">
                                            <span
                                                className="flex h-11 w-11 items-center justify-center rounded-2xl text-white"
                                                style={{ background: hue }}
                                            >
                                                <IconSparkle size={20} />
                                            </span>
                                            <div>
                                                <h3 className="text-ink font-semibold dark:text-white">
                                                    {g.name}
                                                </h3>
                                                <p className="text-ink-faint text-xs">
                                                    {formatMoney(g.saved, {
                                                        currency,
                                                    })}{' '}
                                                    /{' '}
                                                    {formatMoney(g.target, {
                                                        currency,
                                                    })}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex flex-col items-end gap-1">
                                            {g.is_completed ? (
                                                <Badge tone="success">
                                                    {t('goals.completed')}
                                                </Badge>
                                            ) : null}
                                            {g.on_track === false ? (
                                                <Badge tone="warning">
                                                    {t('goals.off_track')}
                                                </Badge>
                                            ) : null}
                                        </div>
                                    </div>

                                    <div className="mt-4">
                                        <Progress
                                            value={Number(g.progress_percent)}
                                            tone={
                                                g.is_completed
                                                    ? 'success'
                                                    : 'brand'
                                            }
                                            showLabel
                                        />
                                    </div>

                                    <div className="mt-3 grid grid-cols-3 gap-2 text-center text-xs">
                                        <div className="bg-surface-strong rounded-xl p-2 dark:bg-white/5">
                                            <p className="text-ink-faint">
                                                {t('goals.day')}
                                            </p>
                                            <p className="text-ink font-semibold dark:text-white">
                                                {g.required_daily
                                                    ? formatMoney(
                                                          g.required_daily,
                                                          { currency },
                                                      )
                                                    : '—'}
                                            </p>
                                        </div>
                                        <div className="bg-surface-strong rounded-xl p-2 dark:bg-white/5">
                                            <p className="text-ink-faint">
                                                {t('goals.week')}
                                            </p>
                                            <p className="text-ink font-semibold dark:text-white">
                                                {g.required_weekly
                                                    ? formatMoney(
                                                          g.required_weekly,
                                                          { currency },
                                                      )
                                                    : '—'}
                                            </p>
                                        </div>
                                        <div className="bg-surface-strong rounded-xl p-2 dark:bg-white/5">
                                            <p className="text-ink-faint">
                                                {t('goals.month')}
                                            </p>
                                            <p className="text-ink font-semibold dark:text-white">
                                                {g.required_monthly
                                                    ? formatMoney(
                                                          g.required_monthly,
                                                          { currency },
                                                      )
                                                    : '—'}
                                            </p>
                                        </div>
                                    </div>

                                    {g.deadline ? (
                                        <p className="text-ink-faint mt-3 text-xs">
                                            {t('goals.deadline')}: {g.deadline}{' '}
                                            {g.days_remaining !== null
                                                ? `(${t('goals.days_left', { n: g.days_remaining })})`
                                                : ''}
                                        </p>
                                    ) : null}

                                    <div className="mt-4 flex justify-between gap-2">
                                        <Button
                                            variant="soft"
                                            size="sm"
                                            onClick={() => {
                                                setContributing(g);
                                                setContributeAmount('');
                                                setContributeAccountId('');
                                            }}
                                        >
                                            {t('goals.contribute')}
                                        </Button>
                                        <div className="flex gap-2 opacity-100 transition sm:opacity-0 sm:group-hover:opacity-100">
                                            <Button
                                                variant="secondary"
                                                size="sm"
                                                onClick={() => openEdit(g)}
                                            >
                                                {t('common.edit')}
                                            </Button>
                                            <Button
                                                variant="danger"
                                                size="sm"
                                                onClick={() => setDeleting(g)}
                                            >
                                                {t('common.delete')}
                                            </Button>
                                        </div>
                                    </div>
                                </Card>
                            );
                        })}
                    </div>
                </>
            )}

            <Dialog
                open={dialogOpen}
                onClose={() => setDialogOpen(false)}
                title={editing ? t('goals.edit') : t('goals.add')}
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
                            disabled={!form.name || !form.target_amount}
                        >
                            {editing ? t('common.save') : t('common.create')}
                        </Button>
                    </div>
                }
            >
                <div className="space-y-4">
                    <Input
                        label={t('goals.name')}
                        value={form.name}
                        onChange={(e) =>
                            setForm({ ...form, name: e.target.value })
                        }
                        placeholder={t('goals.name_placeholder')}
                        error={errors.name}
                    />
                    <Input
                        label={t('goals.target')}
                        type="number"
                        min="0"
                        step="0.01"
                        value={form.target_amount}
                        onChange={(e) =>
                            setForm({ ...form, target_amount: e.target.value })
                        }
                        error={errors.target_amount}
                    />
                    <Input
                        label={t('goals.deadline')}
                        type="date"
                        value={form.deadline}
                        onChange={(e) =>
                            setForm({ ...form, deadline: e.target.value })
                        }
                    />
                    <Input
                        label={t('goals.description')}
                        value={form.description}
                        onChange={(e) =>
                            setForm({ ...form, description: e.target.value })
                        }
                    />
                </div>
            </Dialog>

            <Dialog
                open={contributing !== null}
                onClose={() => setContributing(null)}
                title={t('goals.contribute')}
                footer={
                    <div className="flex justify-end gap-2">
                        <Button
                            variant="secondary"
                            onClick={() => setContributing(null)}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button
                            onClick={submitContribution}
                            disabled={!contributeAmount || !contributeAccountId}
                        >
                            {t('common.add')}
                        </Button>
                    </div>
                }
            >
                <div className="space-y-3">
                    {contributing ? (
                        <>
                            <p className="text-ink-soft text-sm">
                                {t('goals.contribute_hint', {
                                    name: contributing.name,
                                })}
                            </p>
                            <Select
                                label={t('goals.from_account')}
                                options={accounts
                                    .filter(
                                        (a) =>
                                            String(a.id) !==
                                            String(contributing.account_id),
                                    )
                                    .map((a) => ({
                                        value: a.id,
                                        label: `${a.name} · ${formatMoney(a.balance, { currency })}`,
                                    }))}
                                value={contributeAccountId}
                                onChange={(e) =>
                                    setContributeAccountId(e.target.value)
                                }
                                placeholder={t('common.select')}
                            />
                            <p className="text-ink-faint text-xs">
                                {t('goals.from_account_hint')}
                            </p>
                            <Input
                                label={t('goals.amount')}
                                type="number"
                                min="0"
                                step="0.01"
                                value={contributeAmount}
                                onChange={(e) =>
                                    setContributeAmount(e.target.value)
                                }
                                autoFocus
                            />
                        </>
                    ) : null}
                </div>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onClose={() => setDeleting(null)}
                onConfirm={confirmDelete}
                title={t('goals.delete_title')}
                message={t('goals.delete_hint')}
                confirmLabel={t('common.delete')}
            />
        </AppLayout>
    );
}
