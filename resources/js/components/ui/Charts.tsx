import { formatMoney } from '@/lib/money';
import { useTrans } from '@/lib/translation';

/** Grouped bar chart for income vs expenses. Pure SVG, direction-agnostic. */
export function GroupedBarChart({
    data,
    currency = 'MAD',
    height = 200,
}: {
    data: { label: string; income: number; expenses: number }[];
    currency?: string;
    height?: number;
}) {
    const t = useTrans();
    const max = Math.max(1, ...data.flatMap((d) => [d.income, d.expenses]));
    const slot = data.length ? 100 / data.length : 100;
    const barWidth = Math.min(16, slot * 0.26);

    return (
        <div
            className="w-full"
            role="img"
            aria-label={t('charts.income_vs_expenses')}
        >
            <div className="mb-3 flex items-center justify-end gap-4 text-xs">
                <span className="text-ink-soft flex items-center gap-1.5">
                    <span className="bg-accent h-2.5 w-2.5 rounded-[3px]" />
                    {t('charts.income')}
                </span>
                <span className="text-ink-soft flex items-center gap-1.5">
                    <span className="bg-ink-faint h-2.5 w-2.5 rounded-[3px]" />
                    {t('charts.expenses')}
                </span>
            </div>
            <svg
                viewBox={`0 0 ${data.length * 50} ${height}`}
                className="w-full"
                style={{ height }}
                preserveAspectRatio="none"
            >
                {data.map((d, i) => {
                    const center = i * 50 + 25;
                    const incomeH = (d.income / max) * (height - 36);
                    const expenseH = (d.expenses / max) * (height - 36);
                    return (
                        <g key={d.label} className="group">
                            <rect
                                x={center - barWidth - 2}
                                y={height - 32 - incomeH}
                                width={barWidth}
                                height={incomeH}
                                rx={3}
                                fill="var(--color-accent)"
                                opacity="0.9"
                            />
                            <rect
                                x={center + 2}
                                y={height - 32 - expenseH}
                                width={barWidth}
                                height={expenseH}
                                rx={3}
                                fill="var(--color-ink-faint)"
                                opacity="0.55"
                            />
                        </g>
                    );
                })}
            </svg>
            <div className="text-ink-faint mt-1.5 flex justify-between text-[11px]">
                {data.map((d, i) => (
                    <span
                        key={d.label}
                        style={{ width: `${slot}%`, textAlign: 'center' }}
                        className="truncate"
                    >
                        {d.label}
                    </span>
                ))}
            </div>
        </div>
    );
}

const PALETTE = [
    'var(--color-accent)',
    'var(--color-ink-faint)',
    '#b8860b',
    'var(--color-coral)',
    'var(--color-mint)',
    '#7c6aff',
    'var(--color-ink-soft)',
    '#c2701a',
    'var(--color-cyan-neon)',
    '#5b6bd6',
    'var(--color-ink)',
    '#a05bb5',
];

/** Horizontal bar breakdown (e.g. spending by category). */
export function BreakdownList({
    data,
    currency = 'MAD',
}: {
    data: {
        name: string;
        amount: number;
        share: number;
        color?: string | null;
    }[];
    currency?: string;
}) {
    const t = useTrans();
    const total = data.reduce((s, d) => s + d.amount, 0);

    if (total <= 0) {
        return (
            <p className="text-ink-faint py-6 text-center text-sm">
                {t('charts.no_spending')}
            </p>
        );
    }

    return (
        <div className="space-y-3.5">
            {data.slice(0, 7).map((d, i) => (
                <div key={d.name + i}>
                    <div className="mb-1.5 flex items-center justify-between text-sm">
                        <span className="text-ink flex min-w-0 items-center gap-2">
                            <span
                                className="h-2 w-2 shrink-0 rounded-full"
                                style={{
                                    background:
                                        d.color ?? PALETTE[i % PALETTE.length],
                                }}
                            />
                            <span className="truncate">{d.name}</span>
                        </span>
                        <span className="text-ink-soft shrink-0 font-medium tabular-nums">
                            {formatMoney(d.amount, { currency })}
                        </span>
                    </div>
                    <div className="bg-surface-strong h-1.5 w-full overflow-hidden rounded-full">
                        <div
                            className="h-full rounded-full transition-all duration-500"
                            style={{
                                width: `${d.share}%`,
                                background:
                                    d.color ?? PALETTE[i % PALETTE.length],
                            }}
                        />
                    </div>
                </div>
            ))}
        </div>
    );
}

/** Donut chart for shares. */
export function DonutChart({
    data,
    size = 140,
    centerLabel,
    centerValue,
}: {
    data: { value: number; color?: string | null }[];
    size?: number;
    centerLabel?: string;
    centerValue?: string;
}) {
    const t = useTrans();
    const total = data.reduce((s, d) => s + d.value, 0);
    const r = 42;
    const c = 2 * Math.PI * r;
    let offset = 0;

    return (
        <div
            className="relative inline-flex"
            role="img"
            aria-label={centerLabel ?? t('charts.donut')}
        >
            <svg
                width={size}
                height={size}
                viewBox="0 0 100 100"
                className="-rotate-90"
            >
                <circle
                    cx="50"
                    cy="50"
                    r={r}
                    fill="none"
                    stroke="var(--color-ink-faint)"
                    strokeOpacity="0.12"
                    strokeWidth="12"
                />
                {total > 0 &&
                    data.map((d, i) => {
                        const frac = d.value / total;
                        const dash = frac * c;
                        const seg = (
                            <circle
                                key={i}
                                cx="50"
                                cy="50"
                                r={r}
                                fill="none"
                                stroke={d.color ?? PALETTE[i % PALETTE.length]}
                                strokeWidth="12"
                                strokeDasharray={`${dash} ${c - dash}`}
                                strokeDashoffset={-offset}
                            />
                        );
                        offset += dash;
                        return seg;
                    })}
            </svg>
            <div className="absolute inset-0 flex flex-col items-center justify-center">
                {centerValue ? (
                    <span className="text-ink text-lg font-semibold tracking-tight tabular-nums">
                        {centerValue}
                    </span>
                ) : null}
                {centerLabel ? (
                    <span className="text-ink-faint text-[10px]">
                        {centerLabel}
                    </span>
                ) : null}
            </div>
        </div>
    );
}

/** Line/area chart for balance history. */
export function AreaChart({
    points,
    currency = 'MAD',
    height = 200,
}: {
    points: { label: string; value: number }[];
    currency?: string;
    height?: number;
}) {
    const t = useTrans();
    const width = 300;
    const pad = 10;
    const max = Math.max(1, ...points.map((p) => p.value));
    const min = Math.min(0, ...points.map((p) => p.value));

    const x = (i: number) =>
        pad + (i / Math.max(1, points.length - 1)) * (width - pad * 2);
    const y = (v: number) =>
        height -
        pad -
        ((v - min) / Math.max(1, max - min)) * (height - pad * 2);

    const line = points
        .map(
            (p, i) =>
                `${i === 0 ? 'M' : 'L'}${x(i).toFixed(1)},${y(p.value).toFixed(1)}`,
        )
        .join(' ');
    const area = `${line} L${x(points.length - 1).toFixed(1)},${height} L${x(0).toFixed(1)},${height} Z`;

    return (
        <svg
            viewBox={`0 0 ${width} ${height}`}
            className="w-full"
            style={{ height }}
            preserveAspectRatio="none"
            role="img"
            aria-label={t('charts.balance_history')}
        >
            <defs>
                <linearGradient id="areaGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop
                        offset="0%"
                        stopColor="var(--color-accent)"
                        stopOpacity="0.2"
                    />
                    <stop
                        offset="100%"
                        stopColor="var(--color-accent)"
                        stopOpacity="0"
                    />
                </linearGradient>
            </defs>
            <path d={area} fill="url(#areaGrad)" />
            <path
                d={line}
                fill="none"
                stroke="var(--color-accent)"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}
