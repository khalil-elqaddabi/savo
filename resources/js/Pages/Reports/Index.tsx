import AppLayout from '@/Layouts/AppLayout';
import {
    IconArrowDownRight,
    IconArrowUpRight,
    IconTrendingDown,
    IconTrendingUp,
} from '@/components/Icons';
import { StatCard } from '@/components/finance/StatCard';
import { Button, Card, Input, Progress, Select } from '@/components/ui';
import {
    AreaChart,
    BreakdownList,
    GroupedBarChart,
} from '@/components/ui/Charts';
import { PageHeader } from '@/components/ui/PageHeader';
import { formatMoney } from '@/lib/money';
import { useTrans } from '@/lib/translation';
import type { SharedProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface Props extends SharedProps {
    period: { from: string; to: string; key: string };
    summary: any;
    previousSummary: any;
    byCategory: any[];
    byAccount: any[];
    compare: any;
    balanceHistory: any[];
    monthly: any[];
    budgets: any[];
    goals: any[];
}

export default function ReportsIndex() {
    const t = useTrans();
    const {
        app,
        period,
        summary,
        previousSummary,
        byCategory,
        byAccount,
        compare,
        balanceHistory,
        monthly,
        budgets,
        goals,
    } = usePage<Props>().props;
    const currency = app.currency;

    const [periodKey, setPeriodKey] = useState(period.key || 'month');
    const [custom, setCustom] = useState({ from: period.from, to: period.to });

    const changePeriod = (key: string) => {
        setPeriodKey(key);
        router.get(
            '/reports',
            { period: key },
            { preserveState: true, replace: true },
        );
    };

    const applyCustom = () => {
        router.get(
            '/reports',
            { period: 'custom', from: custom.from, to: custom.to },
            { preserveState: true, replace: true },
        );
    };

    const prevIncome = Number(
        compare?.current?.income ?? Number(previousSummary?.income ?? 0),
    );
    const lastIncome = Number(previousSummary?.income ?? 0);
    const incomeDelta =
        lastIncome > 0 ? ((prevIncome - lastIncome) / lastIncome) * 100 : null;

    const balancePoints = (balanceHistory ?? []).map((b: any) => ({
        label: b.date ?? b.label ?? '',
        value: Number(b.balance ?? b.value ?? 0),
    }));

    const summaryCards = [
        {
            label: t('reports.income'),
            value: summary?.income ?? 0,
            icon: <IconArrowDownRight size={18} />,
            color: '#10b981',
            type: 'income',
        },
        {
            label: t('reports.expenses'),
            value: summary?.expenses ?? 0,
            icon: <IconArrowUpRight size={18} />,
            color: '#ef4444',
            type: 'expense',
        },
        {
            label: t('reports.net'),
            value: summary?.net ?? 0,
            icon: <IconTrendingUp size={18} />,
            color: '#6366f1',
            type: 'net',
        },
        {
            label: t('reports.savings_rate'),
            value: summary?.savings_rate ?? 0,
            suffix: '%',
            icon: <IconTrendingDown size={18} />,
            color: '#f59e0b',
            type: 'rate',
        },
    ];

    return (
        <AppLayout>
            <PageHeader
                title={t('reports.title')}
                subtitle={t('reports.subtitle')}
            />

            {/* Period selector */}
            <Card className="mb-5">
                <div className="flex flex-wrap items-end gap-3">
                    <Select
                        label={t('reports.period')}
                        options={[
                            {
                                value: 'month',
                                label: t('reports.period_month'),
                            },
                            { value: 'week', label: t('reports.period_week') },
                            {
                                value: 'prev_month',
                                label: t('reports.period_prev_month'),
                            },
                            { value: 'year', label: t('reports.period_year') },
                            {
                                value: 'custom',
                                label: t('reports.period_custom'),
                            },
                        ]}
                        value={periodKey}
                        onChange={(e) => changePeriod(e.target.value)}
                        className="w-44"
                    />
                    {periodKey === 'custom' ? (
                        <>
                            <Input
                                type="date"
                                label={t('reports.from')}
                                value={custom.from}
                                onChange={(e) =>
                                    setCustom({
                                        ...custom,
                                        from: e.target.value,
                                    })
                                }
                            />
                            <Input
                                type="date"
                                label={t('reports.to')}
                                value={custom.to}
                                onChange={(e) =>
                                    setCustom({ ...custom, to: e.target.value })
                                }
                            />
                            <Button onClick={applyCustom}>
                                {t('reports.apply')}
                            </Button>
                        </>
                    ) : (
                        <p className="text-ink-faint pb-2 text-sm">
                            {period.from} {app.isRtl ? '←' : '→'} {period.to}
                        </p>
                    )}
                </div>
            </Card>

            {/* Stat cards */}
            <div className="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
                {summaryCards.map((c) => (
                    <StatCard
                        key={c.label}
                        label={c.label}
                        value={
                            c.type === 'rate'
                                ? `${c.value}${c.suffix}`
                                : formatMoney(c.value, { currency })
                        }
                        icon={c.icon}
                        accent={c.color}
                        hint={
                            c.type === 'net'
                                ? t('reports.period_summary')
                                : undefined
                        }
                        positive={
                            c.type === 'net'
                                ? Number(c.value) >= 0
                                : c.type === 'income'
                        }
                    />
                ))}
            </div>

            <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
                <Card
                    className="lg:col-span-2"
                    title={t('reports.last_6_months')}
                >
                    <GroupedBarChart
                        data={(monthly ?? []).map((m) => ({
                            label: m.label,
                            income: Number(m.income),
                            expenses: Number(m.expenses),
                        }))}
                        currency={currency}
                    />
                </Card>

                <Card title={t('reports.balance_history')}>
                    {balancePoints.length > 0 ? (
                        <AreaChart points={balancePoints} currency={currency} />
                    ) : (
                        <p className="text-ink-faint text-sm">
                            {t('reports.no_data')}
                        </p>
                    )}
                </Card>
            </div>

            <div className="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
                <Card title={t('reports.by_category')}>
                    <BreakdownList
                        data={byCategory.map((c) => ({
                            name: c.name,
                            amount: Number(c.amount),
                            share: Number(c.share),
                            color: c.color,
                        }))}
                        currency={currency}
                    />
                </Card>

                <Card title={t('reports.by_account')}>
                    {byAccount.length === 0 ? (
                        <p className="text-ink-faint text-sm">
                            {t('reports.no_data')}
                        </p>
                    ) : (
                        <div className="space-y-3">
                            {byAccount.map((a, i) => (
                                <div
                                    key={a.account_id ?? i}
                                    className="flex items-center justify-between text-sm"
                                >
                                    <span className="text-ink font-medium dark:text-white">
                                        {a.name}
                                    </span>
                                    <span className="text-ink-soft">
                                        -{formatMoney(a.amount, { currency })}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>
            </div>

            <div className="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-3">
                <Card title={t('reports.budget_status')}>
                    {budgets.length === 0 ? (
                        <p className="text-ink-faint text-sm">
                            {t('reports.no_budgets')}
                        </p>
                    ) : (
                        <div className="space-y-4">
                            {budgets.map((b, i) => (
                                <div key={i}>
                                    <div className="mb-1 flex justify-between text-sm">
                                        <span className="text-ink font-medium dark:text-white">
                                            {b.name}
                                        </span>
                                        <span className="text-ink-faint text-xs">
                                            {formatMoney(b.spent, { currency })}{' '}
                                            /{' '}
                                            {formatMoney(b.amount, {
                                                currency,
                                            })}
                                        </span>
                                    </div>
                                    <Progress
                                        value={Number(b.percent)}
                                        tone={
                                            b.status === 'exceeded'
                                                ? 'danger'
                                                : b.status === 'critical'
                                                  ? 'danger'
                                                  : b.status === 'warning'
                                                    ? 'warning'
                                                    : 'success'
                                        }
                                    />
                                </div>
                            ))}
                        </div>
                    )}
                </Card>

                <Card title={t('reports.goals_progress')}>
                    {goals.length === 0 ? (
                        <p className="text-ink-faint text-sm">
                            {t('reports.no_goals')}
                        </p>
                    ) : (
                        <div className="space-y-4">
                            {goals.map((g) => (
                                <div key={g.goal?.id ?? g.name}>
                                    <div className="mb-1 flex justify-between text-sm">
                                        <span className="text-ink font-medium dark:text-white">
                                            {g.name}
                                        </span>
                                        <span className="text-ink-faint text-xs">
                                            {g.progress_percent}%
                                        </span>
                                    </div>
                                    <Progress
                                        value={Number(g.progress_percent)}
                                        tone="brand"
                                    />
                                </div>
                            ))}
                        </div>
                    )}
                </Card>

                <Card title={t('reports.avg_daily')}>
                    <div className="space-y-3 text-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-ink-faint">
                                {t('reports.avg_daily_spend')}
                            </span>
                            <span className="text-ink font-semibold dark:text-white">
                                {formatMoney(summary?.avg_daily_spend ?? 0, {
                                    currency,
                                })}
                            </span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-ink-faint">
                                {t('reports.transactions_count')}
                            </span>
                            <span className="text-ink font-semibold dark:text-white">
                                {summary?.transaction_count ?? 0}
                            </span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-ink-faint">
                                {t('reports.days')}
                            </span>
                            <span className="text-ink font-semibold dark:text-white">
                                {summary?.days ?? 0}
                            </span>
                        </div>
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
