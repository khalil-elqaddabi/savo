import AppLayout from '@/Layouts/AppLayout';
import {
    IconLogout,
    IconPlus,
    IconTarget,
    IconTrash,
    IconUser,
} from '@/components/Icons';
import { TransactionFormDialog } from '@/components/finance/TransactionFormDialog';
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

interface BudgetItem {
    id: number;
    name: string;
    scope: string;
    period: string;
    category_id?: number | null;
    category?: string | null;
    amount: string;
    spent: string;
    remaining: string;
    percent: number;
    status: string;
    period_start: string;
    period_end: string;
    is_owner: boolean;
    role: 'owner' | 'editor' | 'viewer';
    members: BudgetMember[];
}

interface BudgetMember {
    id: number;
    name?: string | null;
    email?: string | null;
    role: string;
}

interface CategoryItem {
    id: number;
    name: string;
    icon?: string | null;
    color?: string | null;
}

interface Props extends SharedProps {
    budgets: BudgetItem[];
    thisMonthExpenses: string;
    categories: CategoryItem[];
}

const statusTone: Record<string, 'success' | 'warning' | 'danger' | 'brand'> = {
    healthy: 'success',
    warning: 'warning',
    critical: 'danger',
    exceeded: 'danger',
};

const progressTone: Record<string, 'success' | 'warning' | 'danger' | 'brand'> =
    {
        healthy: 'success',
        warning: 'warning',
        critical: 'danger',
        exceeded: 'danger',
    };

export default function BudgetsIndex() {
    const t = useTrans();
    const { app, budgets, thisMonthExpenses, categories } =
        usePage<Props>().props;
    const currency = app.currency;

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<BudgetItem | null>(null);
    const [deleting, setDeleting] = useState<BudgetItem | null>(null);
    const [spending, setSpending] = useState<BudgetItem | null>(null);
    const [form, setForm] = useState({
        name: '',
        scope: 'overall',
        category_id: '' as any,
        period: 'monthly',
        amount: '',
        is_active: true,
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    const [sharing, setSharing] = useState<BudgetItem | null>(null);
    const [shareEmail, setShareEmail] = useState('');
    const [shareRole, setShareRole] = useState('viewer');
    const [shareErrors, setShareErrors] = useState<Record<string, string>>({});
    const [addingMember, setAddingMember] = useState(false);

    const [leaving, setLeaving] = useState<BudgetItem | null>(null);
    const [leaveError, setLeaveError] = useState('');
    const [leaveLoading, setLeaveLoading] = useState(false);

    const openLeave = (b: BudgetItem) => {
        setLeaving(b);
        setLeaveError('');
        setLeaveLoading(false);
    };

    const confirmLeave = () => {
        if (!leaving) return;
        setLeaveLoading(true);
        setLeaveError('');
        router.delete(`/budgets/${leaving.id}/members/me`, {
            preserveScroll: true,
            onSuccess: () => setLeaving(null),
            onError: (e) => {
                setLeaveLoading(false);
                setLeaveError(Object.values(e)[0] ?? t('budgets.leave_failed'));
            },
            onFinish: () => setLeaveLoading(false),
        });
    };

    const openShare = (b: BudgetItem) => {
        setSharing(b);
        setShareEmail('');
        setShareRole('viewer');
        setShareErrors({});
    };

    const addMember = () => {
        if (!sharing) return;
        setAddingMember(true);
        setShareErrors({});
        router.post(
            `/budgets/${sharing.id}/members`,
            { email: shareEmail, role: shareRole },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAddingMember(false);
                    setShareEmail('');
                },
                onError: (e) => {
                    setAddingMember(false);
                    setShareErrors(e);
                },
                onFinish: () => setAddingMember(false),
            },
        );
    };

    const removeMember = (budget: BudgetItem, memberId: number) => {
        router.delete(`/budgets/${budget.id}/members/${memberId}`, {
            preserveScroll: true,
        });
    };

    const totalBudget = budgets.reduce((s, b) => s + Number(b.amount), 0);
    const totalSpent = budgets.reduce((s, b) => s + Number(b.spent), 0);

    const openCreate = () => {
        setEditing(null);
        setErrors({});
        setForm({
            name: '',
            scope: 'overall',
            category_id: '',
            period: 'monthly',
            amount: '',
            is_active: true,
        });
        setDialogOpen(true);
    };

    const openEdit = (b: BudgetItem) => {
        setEditing(b);
        setErrors({});
        setForm({
            name: b.name,
            scope: b.scope,
            category_id: b.category_id ?? '',
            period: b.period,
            amount: Number(b.amount).toString(),
            is_active: true,
        });
        setDialogOpen(true);
    };

    const submit = () => {
        setSubmitting(true);
        setErrors({});
        const payload: any = {
            name: form.name,
            scope: form.scope,
            period: form.period,
            amount: form.amount,
        };
        if (form.scope === 'category') payload.category_id = form.category_id;
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
            router.put(`/budgets/${editing.id}`, payload, options);
        } else {
            router.post('/budgets', payload, options);
        }
    };

    const confirmDelete = () => {
        if (!deleting) return;
        router.delete(`/budgets/${deleting.id}`);
        setDeleting(null);
    };

    return (
        <AppLayout>
            <PageHeader
                title={t('budgets.title')}
                subtitle={t('budgets.subtitle')}
                action={
                    <Button onClick={openCreate}>
                        <IconPlus size={16} />
                        {t('budgets.add')}
                    </Button>
                }
            />

            {budgets.length === 0 ? (
                <EmptyState
                    icon={<IconTarget size={28} />}
                    title={t('budgets.empty_title')}
                    description={t('budgets.empty_hint')}
                    action={
                        <Button onClick={openCreate}>
                            <IconPlus size={16} />
                            {t('budgets.add')}
                        </Button>
                    }
                />
            ) : (
                <>
                    {/* Summary statistics bar */}
                    <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="card relative overflow-hidden">
                            <p className="text-ink-faint text-[11px] font-bold tracking-wider uppercase">
                                {t('budgets.total_budget')}
                            </p>
                            <p className="text-ink mt-2 text-2xl font-black dark:text-white">
                                {formatMoney(totalBudget, { currency })}
                            </p>
                        </div>
                        <div className="card relative overflow-hidden">
                            <p className="text-ink-faint text-[11px] font-bold tracking-wider uppercase">
                                {t('budgets.total_spent')}
                            </p>
                            <p className="text-coral mt-2 text-2xl font-black">
                                {formatMoney(totalSpent, { currency })}
                            </p>
                        </div>
                        <div className="card relative overflow-hidden">
                            <p className="text-ink-faint text-[11px] font-bold tracking-wider uppercase">
                                {t('budgets.month_expenses')}
                            </p>
                            <p className="text-ink mt-2 text-2xl font-black dark:text-white">
                                {formatMoney(thisMonthExpenses, {
                                    currency,
                                })}
                            </p>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                        {budgets.map((b) => (
                            <Card
                                key={b.id}
                                className="group hover:shadow-lift relative overflow-hidden transition-all duration-300"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 className="text-ink text-base font-bold dark:text-white">
                                            {b.name}
                                        </h3>
                                        <p className="text-ink-faint mt-0.5 text-xs font-semibold">
                                            {b.scope === 'category'
                                                ? b.category
                                                : t('budgets.overall')}
                                            {b.period === 'weekly'
                                                ? ` · ${t('budgets.weekly')}`
                                                : ` · ${t('budgets.monthly')}`}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {!b.is_owner ? (
                                            <Badge tone="brand">
                                                {t('budgets.shared')}
                                            </Badge>
                                        ) : null}
                                        <Badge
                                            tone={
                                                statusTone[b.status] ?? 'brand'
                                            }
                                        >
                                            {t(`budgets.status_${b.status}`)}
                                        </Badge>
                                    </div>
                                </div>

                                <div className="mt-5">
                                    <Progress
                                        value={Number(b.percent)}
                                        tone={progressTone[b.status] ?? 'brand'}
                                        showLabel
                                    />
                                </div>

                                <div className="bg-surface-strong/40 mt-4 flex items-center justify-between rounded-xl p-3 text-xs font-semibold dark:bg-white/[0.03]">
                                    <span className="text-ink-faint">
                                        {t('budgets.spend_of')}:{' '}
                                        <strong className="text-ink dark:text-white">
                                            {formatMoney(b.spent, { currency })}
                                        </strong>{' '}
                                        / {formatMoney(b.amount, { currency })}
                                    </span>
                                    <span className="text-ink text-sm font-extrabold dark:text-white">
                                        {formatMoney(b.remaining, { currency })}
                                    </span>
                                </div>

                                <div className="mt-4 flex justify-end gap-2">
                                    {b.role === 'viewer' ? null : (
                                        <Button
                                            variant="soft"
                                            size="sm"
                                            onClick={() => setSpending(b)}
                                        >
                                            {t('budgets.spend')}
                                        </Button>
                                    )}
                                    {b.is_owner ? (
                                        <>
                                            <Button
                                                variant="secondary"
                                                size="sm"
                                                onClick={() => openShare(b)}
                                            >
                                                <IconUser size={15} />
                                                {t('budgets.share')}
                                            </Button>
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
                                        </>
                                    ) : b.role === 'editor' ? (
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            onClick={() => openEdit(b)}
                                        >
                                            {t('common.edit')}
                                        </Button>
                                    ) : null}
                                    {b.is_owner ? null : (
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            onClick={() => openLeave(b)}
                                        >
                                            <IconLogout size={15} />
                                            {t('budgets.leave')}
                                        </Button>
                                    )}
                                </div>
                            </Card>
                        ))}
                    </div>
                </>
            )}

            <Dialog
                open={dialogOpen}
                onClose={() => setDialogOpen(false)}
                title={editing ? t('budgets.edit') : t('budgets.add')}
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
                        label={t('budgets.name')}
                        value={form.name}
                        onChange={(e) =>
                            setForm({ ...form, name: e.target.value })
                        }
                        placeholder={t('budgets.name_placeholder')}
                        error={errors.name}
                    />
                    <Select
                        label={t('budgets.scope')}
                        options={[
                            {
                                value: 'overall',
                                label: t('budgets.scope_overall'),
                            },
                            {
                                value: 'category',
                                label: t('budgets.scope_category'),
                            },
                        ]}
                        value={form.scope}
                        onChange={(e) =>
                            setForm({ ...form, scope: e.target.value })
                        }
                    />
                    {form.scope === 'category' ? (
                        <Select
                            label={t('budgets.category')}
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
                    ) : null}
                    <Select
                        label={t('budgets.period')}
                        options={[
                            { value: 'monthly', label: t('budgets.monthly') },
                            { value: 'weekly', label: t('budgets.weekly') },
                        ]}
                        value={form.period}
                        onChange={(e) =>
                            setForm({ ...form, period: e.target.value })
                        }
                    />
                    <Input
                        label={t('budgets.amount')}
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
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onClose={() => setDeleting(null)}
                onConfirm={confirmDelete}
                title={t('budgets.delete_title')}
                message={t('budgets.delete_hint')}
                confirmLabel={t('common.delete')}
            />

            <ConfirmDialog
                open={leaving !== null}
                onClose={() => setLeaving(null)}
                onConfirm={confirmLeave}
                title={t('budgets.leave_title')}
                message={
                    leaveError
                        ? `${t('budgets.leave_confirm')}\n\n${leaveError}`
                        : t('budgets.leave_confirm')
                }
                confirmLabel={t('budgets.leave')}
                loading={leaveLoading}
            />

            <TransactionFormDialog
                open={spending !== null}
                onClose={() => setSpending(null)}
                fixedType="expense"
                title={t('budgets.spend')}
                lockedCategoryId={
                    spending?.scope === 'category'
                        ? (spending.category_id ?? undefined)
                        : undefined
                }
            />

            <Dialog
                open={sharing !== null}
                onClose={() => setSharing(null)}
                title={sharing ? t('budgets.share_title') : ''}
            >
                <div className="space-y-4">
                    {sharing && sharing.members.length > 0 ? (
                        <div className="space-y-2">
                            <p className="text-ink-faint text-xs font-semibold">
                                {t('budgets.members')}
                            </p>
                            {sharing.members.map((m) => (
                                <div
                                    key={m.id}
                                    className="bg-surface-strong/40 flex items-center justify-between rounded-lg px-3 py-2 dark:bg-white/[0.03]"
                                >
                                    <div className="min-w-0">
                                        <p className="text-ink truncate text-sm font-medium dark:text-white">
                                            {m.name || m.email}
                                        </p>
                                        <p className="text-ink-faint truncate text-xs">
                                            {m.email} ·{' '}
                                            {t(`budgets.role_${m.role}`)}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            removeMember(sharing, m.id)
                                        }
                                        className="text-coral p-1.5 hover:opacity-75"
                                        aria-label={t('common.remove')}
                                    >
                                        <IconTrash size={16} />
                                    </button>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-ink-faint text-sm">
                            {t('budgets.share_no_members')}
                        </p>
                    )}

                    <div className="border-ink-faint/20 border-t pt-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <Input
                                label={t('budgets.member_email')}
                                type="email"
                                value={shareEmail}
                                onChange={(e) => setShareEmail(e.target.value)}
                                placeholder="partner@example.com"
                                error={shareErrors.email}
                                className="flex-1"
                            />
                            <Select
                                label={t('budgets.member_role')}
                                options={[
                                    {
                                        value: 'viewer',
                                        label: t('budgets.role_viewer'),
                                    },
                                    {
                                        value: 'editor',
                                        label: t('budgets.role_editor'),
                                    },
                                ]}
                                value={shareRole}
                                onChange={(e) => setShareRole(e.target.value)}
                            />
                            <Button
                                onClick={addMember}
                                loading={addingMember}
                                disabled={!shareEmail}
                            >
                                <IconUser size={15} />
                                {t('budgets.add_member')}
                            </Button>
                        </div>
                    </div>
                </div>
            </Dialog>
        </AppLayout>
    );
}
