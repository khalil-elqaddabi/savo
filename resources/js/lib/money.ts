/**
 * Deterministic money helpers for the client. Amounts arrive from the
 * backend as decimal strings; we format them and never perform authoritative
 * arithmetic on the client (all financial math is done server-side).
 */

const CURRENCY_SYMBOLS: Record<string, string> = {
    MAD: 'DH',
    USD: '$',
    EUR: '€',
    GBP: '£',
    AED: 'د.إ',
    SAR: 'ر.س',
};

/** Map an app locale to a formatting locale that suits it. */
function formatLocale(locale?: string): string | undefined {
    if (!locale) return undefined;
    const base = locale.toLowerCase();
    if (base === 'ar') return 'ar-MA';
    return base;
}

function currentLocale(): string | undefined {
    if (typeof document === 'undefined') return undefined;
    return formatLocale(document.documentElement.lang || undefined);
}

export function moneySymbol(currency: string): string {
    return CURRENCY_SYMBOLS[currency] ?? currency;
}

export interface MoneyOptions {
    currency?: string;
    showSymbol?: boolean;
    decimals?: number;
    sign?: boolean;
}

export function formatMoney(
    value: string | number,
    {
        currency = 'MAD',
        showSymbol = true,
        decimals = 2,
        sign = false,
    }: MoneyOptions = {},
): string {
    const num = Number(value);
    const absolute = Math.abs(num);
    const formatted = absolute.toLocaleString(currentLocale(), {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
        useGrouping: true,
    });

    const prefix = sign ? (num < 0 ? '-' : '+') : num < 0 ? '-' : '';
    const symbol = showSymbol ? ` ${moneySymbol(currency)}` : '';

    return `${prefix}${formatted}${symbol}`;
}

/** Format an amount with sign that reflects its meaning (income positive, expense negative). */
export function formatSigned(
    value: string | number,
    type: 'income' | 'expense' | 'transfer',
    currency = 'MAD',
): string {
    const num = Number(value);
    if (type === 'income') {
        return `+${formatMoney(num, { currency })}`;
    }
    if (type === 'expense') {
        return `-${formatMoney(num, { currency })}`;
    }
    return formatMoney(num, { currency });
}
