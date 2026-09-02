import SettingsLayout from '@/Layouts/SettingsLayout';
import { IconMoon, IconSun } from '@/components/Icons';
import { Badge, Button, Card, Select } from '@/components/ui';
import { useTrans } from '@/lib/translation';
import type { SharedProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const CURRENCY_CODES = ['MAD', 'USD', 'EUR', 'GBP', 'AED', 'SAR'];

export default function SettingsAppearance() {
    const t = useTrans();
    const { app, auth } = usePage<SharedProps>().props;
    const theme = app.theme;
    const currency = auth.user?.currency || app.currency;
    const [selectedCurrency, setSelectedCurrency] = useState(currency);
    const [saving, setSaving] = useState(false);
    const CURRENCIES = CURRENCY_CODES.map((c) => ({
        value: c,
        label: t(`settings.currency_${c}`),
    }));

    const setTheme = (value: 'light' | 'dark') => {
        router.post('/preferences/theme', { theme: value });
    };

    const saveCurrency = () => {
        if (selectedCurrency === currency) return;
        setSaving(true);
        router.post(
            '/preferences/currency',
            { currency: selectedCurrency },
            { onFinish: () => setSaving(false) },
        );
    };

    return (
        <SettingsLayout>
            <Card title={t('settings.theme')}>
                <div className="grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        onClick={() => setTheme('light')}
                        className={`flex flex-col items-center gap-3 rounded-2xl border-2 p-6 transition ${theme === 'light' ? 'border-brand-500 bg-brand-50 dark:bg-brand-500/10' : 'border-line hover:border-brand-300 dark:border-white/10'}`}
                    >
                        <div className="border-line flex h-20 w-full flex-col gap-2 rounded-lg border bg-white p-3 dark:border-white/10">
                            <span className="h-2 w-1/3 rounded bg-slate-200" />
                            <span className="h-2 w-2/3 rounded bg-slate-100" />
                            <span className="h-2 w-1/2 rounded bg-slate-100" />
                        </div>
                        <span className="text-ink flex items-center gap-2 text-sm font-medium dark:text-white">
                            <IconSun size={16} />
                            {t('settings.light')}
                        </span>
                        {theme === 'light' ? (
                            <Badge tone="brand">{t('common.active')}</Badge>
                        ) : null}
                    </button>
                    <button
                        type="button"
                        onClick={() => setTheme('dark')}
                        className={`flex flex-col items-center gap-3 rounded-2xl border-2 p-6 transition ${theme === 'dark' ? 'border-brand-500 bg-brand-50 dark:bg-brand-500/10' : 'border-line hover:border-brand-300 dark:border-white/10'}`}
                    >
                        <div className="flex h-20 w-full flex-col gap-2 rounded-lg border border-white/10 bg-slate-800 p-3">
                            <span className="h-2 w-1/3 rounded bg-slate-600" />
                            <span className="h-2 w-2/3 rounded bg-slate-700" />
                            <span className="h-2 w-1/2 rounded bg-slate-700" />
                        </div>
                        <span className="text-ink flex items-center gap-2 text-sm font-medium dark:text-white">
                            <IconMoon size={16} />
                            {t('settings.dark')}
                        </span>
                        {theme === 'dark' ? (
                            <Badge tone="brand">{t('common.active')}</Badge>
                        ) : null}
                    </button>
                </div>
            </Card>

            <Card
                title={t('settings.currency')}
                subtitle={t('settings.currency_hint')}
            >
                <div className="space-y-4">
                    <Select
                        label={t('settings.currency')}
                        options={CURRENCIES}
                        value={selectedCurrency}
                        onChange={(e) => setSelectedCurrency(e.target.value)}
                    />
                    <div className="flex justify-end">
                        <Button
                            onClick={saveCurrency}
                            loading={saving}
                            disabled={selectedCurrency === currency}
                        >
                            {t('common.save')}
                        </Button>
                    </div>
                </div>
            </Card>
        </SettingsLayout>
    );
}
