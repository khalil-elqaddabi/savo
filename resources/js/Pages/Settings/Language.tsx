import SettingsLayout from '@/Layouts/SettingsLayout';
import { IconGlobe } from '@/components/Icons';
import { Badge, Card } from '@/components/ui';
import { useTrans } from '@/lib/translation';
import type { SharedProps } from '@/types';
import { router, usePage } from '@inertiajs/react';

export default function SettingsLanguage() {
    const t = useTrans();
    const { app } = usePage<SharedProps>().props;

    const setLocale = (code: string) =>
        router.post('/preferences/language', { locale: code });

    return (
        <SettingsLayout>
            <Card
                title={t('settings.language')}
                subtitle={t('settings.language_hint')}
            >
                <div className="space-y-3">
                    {app.supportedLocales.map((l) => {
                        const active = app.locale === l.code;
                        return (
                            <button
                                key={l.code}
                                type="button"
                                onClick={() => setLocale(l.code)}
                                className={`flex w-full items-center justify-between rounded-2xl border-2 p-4 text-start transition ${active ? 'border-brand-500 bg-brand-50 dark:bg-brand-500/10' : 'border-line hover:border-brand-300 dark:border-white/10'}`}
                            >
                                <div className="flex items-center gap-3">
                                    <span className="bg-surface-strong flex h-10 w-10 items-center justify-center rounded-xl dark:bg-white/5">
                                        <IconGlobe size={20} />
                                    </span>
                                    <div>
                                        <p className="text-ink font-semibold dark:text-white">
                                            {l.name}
                                        </p>
                                        <p className="text-ink-faint text-xs uppercase">
                                            {l.code}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    {l.dir === 'rtl' ? (
                                        <Badge tone="info">
                                            {t('settings.rtl')}
                                        </Badge>
                                    ) : null}
                                    {active ? (
                                        <Badge tone="brand">
                                            {t('common.active')}
                                        </Badge>
                                    ) : null}
                                </div>
                            </button>
                        );
                    })}
                </div>
            </Card>
        </SettingsLayout>
    );
}
