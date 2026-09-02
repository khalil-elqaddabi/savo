import AppLayout from '@/Layouts/AppLayout';
import {
    IconDownload,
    IconGlobe,
    IconLock,
    IconPalette,
    IconUser,
} from '@/components/Icons';
import { useTrans } from '@/lib/translation';
import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

const tabs = [
    {
        href: '/settings/profile',
        key: 'settings.tab_profile',
        icon: IconUser,
    },
    {
        href: '/settings/security',
        key: 'settings.tab_security',
        icon: IconLock,
    },
    {
        href: '/settings/appearance',
        key: 'settings.tab_appearance',
        icon: IconPalette,
    },
    {
        href: '/settings/language',
        key: 'settings.tab_language',
        icon: IconGlobe,
    },
    {
        href: '/settings/data',
        key: 'settings.tab_data',
        icon: IconDownload,
    },
];

export default function SettingsLayout({ children }: { children: ReactNode }) {
    const t = useTrans();
    const path = usePage().url.split('?')[0];

    return (
        <AppLayout isSettings>
            <div className="mb-6">
                <h1 className="text-ink text-xl font-semibold tracking-tight sm:text-2xl">
                    {t('settings.title')}
                </h1>
                <p className="text-ink-faint mt-0.5 text-sm">
                    {t('settings.subtitle')}
                </p>
            </div>

            <div className="border-line mb-6 flex gap-1 overflow-x-auto border-b pb-2">
                {tabs.map((tab) => {
                    const active = path === tab.href;
                    const Icon = tab.icon;
                    return (
                        <Link
                            key={tab.href}
                            href={tab.href}
                            className={`flex shrink-0 items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium whitespace-nowrap transition ${
                                active
                                    ? 'bg-accent-soft text-accent'
                                    : 'text-ink-soft hover:bg-surface-soft hover:text-ink'
                            }`}
                        >
                            <Icon
                                size={16}
                                className={
                                    active ? 'text-accent' : 'text-ink-faint'
                                }
                            />
                            {t(tab.key)}
                        </Link>
                    );
                })}
            </div>

            <div className="max-w-3xl">{children}</div>
        </AppLayout>
    );
}
