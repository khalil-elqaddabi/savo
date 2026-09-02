import AppLayout from '@/Layouts/AppLayout';
import {
    IconArrowDownRight,
    IconArrowUpRight,
    IconCalendar,
    IconReceipt,
    IconTarget,
    IconTrendingUp,
    IconWallet,
} from '@/components/Icons';
import { AccountCard } from '@/components/finance/AccountCard';
import { StatCard } from '@/components/finance/StatCard';
import { TransactionRow } from '@/components/finance/TransactionRow';
import { Card, EmptyState, PageLoading, Progress } from '@/components/ui';
import { BreakdownList, GroupedBarChart } from '@/components/ui/Charts';
import { formatMoney } from '@/lib/money';
import { useTrans } from '@/lib/translation';
import type { AccountOption, SharedProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';

interface DashboardProps extends SharedProps {
    totalBalance: string;
    accounts: AccountOption[];
    safeToSpend: any;
    monthly: any;
    spendingByCategory: {
        name: string;
        amount: number;
        share: number;
        color?: string | null;
    }[];
    budgets: any[];
    goals: any[];
    forecast: any;
    forecastSeries: any[];
    upcoming: any[];
    recentTransactions: any[];
    insights: { type: string; severity: string; message: string; meta?: any }[];
    health_score: {
        score: number;
        factors: Record<
            string,
            { points: number; max: number; value?: number | null }
        >;
        positive: string[];
        negative: string[];
        generated_at: string;
    };
    upcoming_bills: {
        date: string;
        date_human: string;
        bill: { id: number; name: string; amount: string; currency: string };
    }[];
    debt_summary: {
        total_remaining: string;
        total_original: string;
        monthly_payments: string;
        owed_to_user: string;
        progress: number;
        count: number;
    };
}

const budgetTone: Record<string, 'success' | 'warning' | 'danger' | 'brand'> = {
    healthy: 'success',
    warning: 'warning',
    critical: 'danger',
    exceeded: 'danger',
};

const budgetBarTone: Record<string, string> = {
    healthy: 'text-mint',
    warning: 'text-amberbrand',
    critical: 'text-coral',
    exceeded: 'text-coral',
};

export default function Dashboard() {
    const t = useTrans();
    const { app, auth, ...data } = usePage<DashboardProps>().props;
    const currency = app.currency;
    const d = data as unknown as DashboardProps;

    const healthTone = (s: number) => {
        if (s >= 80) return 'bg-mint/12 text-mint';
        if (s >= 60) return 'bg-amberbrand/12 text-amberbrand';
        if (s >= 40) return 'bg-accent-soft text-accent';
        return 'bg-coral/12 text-coral';
    };

    const healthLabel = (s: number) => {
        if (s >= 80) return t('dashboard.health_excellent');
        if (s >= 60) return t('dashboard.health_good');
        if (s >= 40) return t('dashboard.health_fair');
        return t('dashboard.health_poor');
    };

    if (!d.totalBalance)
        return (
            <AppLayout>
                <PageLoading />
            </AppLayout>
        );

    const monthlyBars = (d.forecastSeries ?? []).map((m) => ({
        label: m.label,
        income: Number(m.income),
        expenses: Number(m.expenses),
    }));

    const safe = d.safeToSpend ?? {};

    const breakdown = [
        {
            key: 'current_balance',
            label: t('dashboard.current_balance'),
            value: d.totalBalance,
            tone: 'text-ink',
        },
        {
            key: 'obligations',
            label: t('dashboard.obligations'),
            value: `-${formatMoney(safe.upcoming_obligations ?? 0, { currency })}`,
            tone: 'text-coral',
        },
        {
            key: 'savings',
            label: t('dashboard.savings'),
            value: `-${formatMoney(safe.planned_savings ?? 0, { currency })}`,
            tone: 'text-amberbrand',
        },
        {
            key: 'protected',
            label: t('dashboard.protected'),
            value: `-${formatMoney(safe.protected_money ?? 0, { currency })}`,
            tone: 'text-accent',
        },
    ];

    return (
        <AppLayout>
            {/* Greeting + date */}
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-ink text-xl font-semibold tracking-tight sm:text-2xl">
                        {t('dashboard.greeting')}
                    </h1>
                    <p className="text-ink-faint mt-0.5 text-sm">
                        {t('dashboard.subtitle')}
                    </p>
                </div>
                <span className="border-line bg-surface text-ink-soft inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium">
                    <span className="bg-mint h-1.5 w-1.5 rounded-full" />
                    {new Date().toLocaleDateString(undefined, {
                        weekday: 'long',
                        month: 'long',
                        day: 'numeric',
                    })}
                </span>
            </div>

            {/* Safe to Spend hero */}
            <section className="border-line bg-surface shadow-card mb-8 overflow-hidden rounded-2xl border">
                <div className="grid lg:grid-cols-[1.4fr_1fr]">
                    {/* Main figure */}
                    <div className="relative p-6 sm:p-8">
                        <div className="flex items-center justify-between">
                            <span className="bg-accent-soft text-accent inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold">
                                <IconTrendingUp size={13} />
                                {t('dashboard.safe_to_spend')}
                            </span>
                            <span className="text-ink-faint text-xs">
                                {t('dashboard.until', {
                                    date: safe.period_end ?? '',
                                })}
                            </span>
                        </div>

                        <p className="text-ink mt-6 text-5xl font-semibold tracking-tight tabular-nums sm:text-6xl">
                            {formatMoney(safe.safe_to_spend ?? 0, { currency })}
                        </p>
                        <p className="text-ink-faint mt-2 text-sm font-medium">
                            {t('dashboard.safe_daily', {
                                amount: formatMoney(
                                    safe.safe_to_spend_daily ?? 0,
                                    { currency },
                                ),
                            })}
                        </p>
                    </div>

                    {/* Breakdown */}
                    <div className="border-line border-t lg:border-s lg:border-t-0">
                        <div className="divide-line lg:divide-line grid grid-cols-2 lg:grid-cols-1 lg:divide-y">
                            {breakdown.map((b) => (
                                <div
                                    key={b.key}
                                    className="border-line border-e p-4 last:border-e-0 sm:p-5 lg:border-e-0 lg:border-b lg:last:border-b-0"
                                >
                                    <p className="micro">{b.label}</p>
                                    <p
                                        className={`mt-1.5 text-lg font-semibold tracking-tight tabular-nums ${b.tone}`}
                                    >
                                        {b.value}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            {/* Overview stats */}
            <div className="mb-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                <StatCard
                    label={t('dashboard.total_balance')}
                    value={formatMoney(d.totalBalance, { currency })}
                    icon={<IconWallet size={18} />}
                />
                <StatCard
                    label={t('dashboard.income_month')}
                    value={formatMoney(d.monthly?.income ?? 0, { currency })}
                    icon={
                        <IconArrowDownRight size={18} className="text-mint" />
                    }
                    delta={d.monthly?.income_delta}
                    deltaLabel={t('dashboard.vs_last')}
                    positive
                />
                <StatCard
                    label={t('dashboard.expenses_month')}
                    value={formatMoney(d.monthly?.expenses ?? 0, { currency })}
                    icon={<IconArrowUpRight size={18} className="text-coral" />}
                    delta={d.monthly?.expense_delta}
                    deltaLabel={t('dashboard.vs_last')}
                    positive={false}
                />
                <StatCard
                    label={t('dashboard.net')}
                    value={formatMoney(d.monthly?.net ?? 0, { currency })}
                    icon={<IconTrendingUp size={18} />}
                    hint={t('dashboard.this_month')}
                />
            </div>

            {/* Financial health, bills & debts */}
            <div className="mb-6 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <Card
                    title={t('dashboard.health_score')}
                    action={
                        <Link
                            href="/bills"
                            className="text-accent text-xs font-semibold transition hover:underline"
                        >
                            {t('common.details')}
                        </Link>
                    }
                >
                    <div className="flex items-center gap-4">
                        <span
                            className={`flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl text-xl font-bold tabular-nums ${healthTone(d.health_score?.score ?? 0)}`}
                        >
                            {d.health_score?.score ?? '—'}
                        </span>
                        <div className="min-w-0">
                            <p className="text-ink text-sm font-semibold">
                                {healthLabel(d.health_score?.score ?? 0)}
                            </p>
                            <p className="text-ink-faint line-clamp-2 text-xs">
                                {d.health_score?.positive?.[0] ??
                                    d.health_score?.negative?.[0] ??
                                    t('dashboard.health_no_data')}
                            </p>
                        </div>
                    </div>
                </Card>

                <Card
                    title={t('dashboard.upcoming_bills')}
                    action={
                        <Link
                            href="/bills"
                            className="text-accent text-xs font-semibold transition hover:underline"
                        >
                            {t('common.view_all')}
                        </Link>
                    }
                >
                    {d.upcoming_bills.length === 0 ? (
                        <EmptyState
                            title={t('dashboard.no_upcoming_bills')}
                            description={t('dashboard.no_upcoming_bills_hint')}
                        />
                    ) : (
                        <div className="space-y-1">
                            {d.upcoming_bills.slice(0, 4).map((u, i) => (
                                <div
                                    key={i}
                                    className="flex items-center gap-3 rounded-xl px-2 py-1.5"
                                >
                                    <span className="bg-accent-soft text-accent flex h-8 w-8 shrink-0 items-center justify-center rounded-lg">
                                        <IconCalendar size={15} />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-ink truncate text-sm font-medium">
                                            {u.bill.name}
                                        </p>
                                        <p className="text-ink-faint text-xs">
                                            {u.date_human}
                                        </p>
                                    </div>
                                    <span className="text-ink shrink-0 text-sm font-semibold tabular-nums">
                                        {formatMoney(u.bill.amount, {
                                            currency: u.bill.currency,
                                        })}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>

                <Card
                    title={t('dashboard.debt_summary')}
                    action={
                        <Link
                            href="/debts"
                            className="text-accent text-xs font-semibold transition hover:underline"
                        >
                            {t('common.view_all')}
                        </Link>
                    }
                >
                    {d.debt_summary.count === 0 &&
                    Number(d.debt_summary.owed_to_user) === 0 ? (
                        <EmptyState title={t('dashboard.no_debts')} />
                    ) : (
                        <div className="space-y-3 text-sm">
                            <div className="bg-surface-soft flex items-center justify-between rounded-xl px-3.5 py-3">
                                <span className="text-ink-faint">
                                    {t('dashboard.debt_remaining')}
                                </span>
                                <span className="text-coral text-[15px] font-semibold tabular-nums">
                                    {formatMoney(
                                        d.debt_summary.total_remaining,
                                        { currency },
                                    )}
                                </span>
                            </div>
                            {[
                                {
                                    label: t('dashboard.debt_monthly'),
                                    value: formatMoney(
                                        d.debt_summary.monthly_payments,
                                        { currency },
                                    ),
                                },
                                {
                                    label: t('dashboard.debt_owed_to_user'),
                                    value: formatMoney(
                                        d.debt_summary.owed_to_user,
                                        { currency },
                                    ),
                                },
                            ].map((row) => (
                                <div
                                    key={row.label}
                                    className="flex items-center justify-between px-1"
                                >
                                    <span className="text-ink-faint">
                                        {row.label}
                                    </span>
                                    <span className="text-ink font-semibold tabular-nums">
                                        {row.value}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>
            </div>

            {/* Chart + breakdown */}
            <div className="mb-6 grid grid-cols-1 gap-5 lg:grid-cols-3">
                <Card
                    className="lg:col-span-2"
                    title={t('dashboard.income_vs_expenses')}
                    subtitle={t('dashboard.last_6_months')}
                >
                    <GroupedBarChart data={monthlyBars} currency={currency} />
                </Card>

                <Card title={t('dashboard.spending_breakdown')}>
                    <BreakdownList
                        data={d.spendingByCategory}
                        currency={currency}
                    />
                </Card>
            </div>

            {/* Budgets, goals, forecast */}
            <div className="mb-6 grid grid-cols-1 gap-5 lg:grid-cols-3">
                <Card
                    title={t('dashboard.budgets')}
                    action={
                        <Link
                            href="/budgets"
                            className="text-accent text-xs font-semibold transition hover:underline"
                        >
                            {t('common.view_all')}
                        </Link>
                    }
                >
                    {d.budgets.length === 0 ? (
                        <EmptyState
                            title={t('dashboard.no_budgets')}
                            description={t('dashboard.no_budgets_hint')}
                            action={
                                <Link
                                    href="/budgets"
                                    className="btn-soft text-xs"
                                >
                                    {t('common.create')}
                                </Link>
                            }
                        />
                    ) : (
                        <div className="space-y-4">
                            {d.budgets.slice(0, 4).map((b) => (
                                <div key={b.id} className="space-y-2">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-ink font-medium">
                                            {b.name}
                                        </span>
                                        <span className="flex items-baseline gap-1 text-xs tabular-nums">
                                            <span
                                                className={`font-semibold ${budgetBarTone[b.status] ?? 'text-ink'}`}
                                            >
                                                {formatMoney(b.spent, {
                                                    currency,
                                                })}
                                            </span>
                                            <span className="text-ink-faint">
                                                /{' '}
                                                {formatMoney(b.amount, {
                                                    currency,
                                                })}
                                            </span>
                                        </span>
                                    </div>
                                    <Progress
                                        value={b.percent}
                                        tone={budgetTone[b.status] ?? 'brand'}
                                    />
                                </div>
                            ))}
                        </div>
                    )}
                </Card>

                <Card
                    title={t('dashboard.goals')}
                    action={
                        <Link
                            href="/goals"
                            className="text-accent text-xs font-semibold transition hover:underline"
                        >
                            {t('common.view_all')}
                        </Link>
                    }
                >
                    {d.goals.length === 0 ? (
                        <EmptyState
                            title={t('dashboard.no_goals')}
                            description={t('dashboard.no_goals_hint')}
                            action={
                                <Link
                                    href="/goals"
                                    className="btn-soft text-xs"
                                >
                                    {t('common.create')}
                                </Link>
                            }
                        />
                    ) : (
                        <div className="space-y-4">
                            {d.goals.slice(0, 3).map((g) => (
                                <div key={g.id} className="space-y-2">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="flex min-w-0 items-center gap-2">
                                            <IconTarget
                                                size={15}
                                                className="text-ink-faint shrink-0"
                                            />
                                            <span className="text-ink truncate font-medium">
                                                {g.name}
                                            </span>
                                        </span>
                                        <span className="text-ink-soft shrink-0 text-xs font-semibold tabular-nums">
                                            {g.progress_percent}%
                                        </span>
                                    </div>
                                    <Progress
                                        value={g.progress_percent}
                                        tone="brand"
                                    />
                                    <p className="text-ink-faint text-xs tabular-nums">
                                        {formatMoney(g.saved, { currency })} /{' '}
                                        {formatMoney(g.target, { currency })}
                                    </p>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>

                <Card
                    title={t('dashboard.forecast')}
                    action={
                        <Link
                            href="/reports"
                            className="text-accent text-xs font-semibold transition hover:underline"
                        >
                            {t('common.details')}
                        </Link>
                    }
                >
                    <div className="space-y-3 text-sm">
                        <div className="bg-surface-soft flex items-center justify-between rounded-xl px-3.5 py-3">
                            <span className="text-ink-faint">
                                {t('dashboard.projected_end')}
                            </span>
                            <span className="text-ink text-[15px] font-semibold tabular-nums">
                                {formatMoney(
                                    d.forecast?.projected_balance ?? 0,
                                    { currency },
                                )}
                            </span>
                        </div>
                        {[
                            {
                                label: t('dashboard.expected_income'),
                                value: `+${formatMoney(d.forecast?.expected_income ?? 0, { currency })}`,
                                cls: 'text-mint',
                            },
                            {
                                label: t('dashboard.expected_expenses'),
                                value: `-${formatMoney(d.forecast?.expected_expenses ?? 0, { currency })}`,
                                cls: 'text-coral',
                            },
                            {
                                label: t('dashboard.planned_savings'),
                                value: `-${formatMoney(d.forecast?.planned_savings ?? 0, { currency })}`,
                                cls: 'text-amberbrand',
                            },
                        ].map((row) => (
                            <div
                                key={row.label}
                                className="flex items-center justify-between px-1"
                            >
                                <span className="text-ink-faint">
                                    {row.label}
                                </span>
                                <span
                                    className={`font-semibold tabular-nums ${row.cls}`}
                                >
                                    {row.value}
                                </span>
                            </div>
                        ))}
                    </div>
                </Card>
            </div>

            {/* Accounts, upcoming, recent transactions */}
            <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
                <Card
                    title={t('dashboard.accounts')}
                    action={
                        <Link
                            href="/accounts"
                            className="text-accent text-xs font-semibold transition hover:underline"
                        >
                            {t('common.view_all')}
                        </Link>
                    }
                >
                    {d.accounts.length === 0 ? (
                        <EmptyState
                            title={t('dashboard.no_accounts')}
                            description={t('dashboard.no_accounts_hint')}
                            action={
                                <Link
                                    href="/accounts"
                                    className="btn-soft text-xs"
                                >
                                    {t('common.create')}
                                </Link>
                            }
                        />
                    ) : (
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            {d.accounts.slice(0, 4).map((a) => (
                                <AccountCard
                                    key={a.id}
                                    account={a}
                                    currency={currency}
                                    compact
                                />
                            ))}
                        </div>
                    )}
                </Card>

                <Card
                    title={t('dashboard.upcoming')}
                    action={
                        <Link
                            href="/recurring"
                            className="text-accent text-xs font-semibold transition hover:underline"
                        >
                            {t('common.view_all')}
                        </Link>
                    }
                >
                    {d.upcoming.length === 0 ? (
                        <EmptyState
                            title={t('dashboard.no_upcoming')}
                            description={t('dashboard.no_upcoming_hint')}
                            action={
                                <Link
                                    href="/recurring"
                                    className="btn-soft text-xs"
                                >
                                    {t('recurring.add')}
                                </Link>
                            }
                        />
                    ) : (
                        <div className="space-y-1">
                            {d.upcoming.slice(0, 5).map((u, i) => (
                                <div
                                    key={i}
                                    className="hover:bg-surface-soft flex items-center gap-3 rounded-xl px-2 py-2 transition"
                                >
                                    <span
                                        className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ${
                                            u.recurring?.type === 'expense'
                                                ? 'bg-coral/10 text-coral'
                                                : 'bg-mint/10 text-mint'
                                        }`}
                                    >
                                        {u.recurring?.type === 'expense' ? (
                                            <IconReceipt size={16} />
                                        ) : (
                                            <IconTrendingUp size={16} />
                                        )}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-ink truncate text-sm font-medium">
                                            {u.recurring?.name}
                                        </p>
                                        <p className="text-ink-faint text-xs">
                                            {u.date_human ?? u.date}
                                        </p>
                                    </div>
                                    <span
                                        className={`text-sm font-semibold tabular-nums ${
                                            u.recurring?.type === 'expense'
                                                ? 'text-ink'
                                                : 'text-mint'
                                        }`}
                                    >
                                        {u.recurring?.type === 'expense'
                                            ? '-'
                                            : '+'}
                                        {formatMoney(u.recurring?.amount ?? 0, {
                                            currency,
                                        })}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>

                <Card
                    title={t('dashboard.recent')}
                    action={
                        <Link
                            href="/transactions"
                            className="text-accent text-xs font-semibold transition hover:underline"
                        >
                            {t('common.view_all')}
                        </Link>
                    }
                >
                    {d.recentTransactions.length === 0 ? (
                        <EmptyState
                            title={t('dashboard.no_transactions')}
                            description={t('dashboard.no_transactions_hint')}
                        />
                    ) : (
                        <div className="divide-line divide-y">
                            {d.recentTransactions.map((tx) => (
                                <TransactionRow
                                    key={tx.id}
                                    tx={tx}
                                    currency={currency}
                                />
                            ))}
                        </div>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
