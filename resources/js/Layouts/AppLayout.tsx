import { usePrefersTheme } from '@/lib/theme';
import { useTrans } from '@/lib/translation';
import type { SharedProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { useCallback, useState, type ReactNode } from 'react';
import { Brand } from '../components/Brand';
import {
    IconBell,
    IconCard,
    IconChart,
    IconChat,
    IconGrid,
    IconHome,
    IconLogout,
    IconMenu,
    IconMoon,
    IconPlus,
    IconReceipt,
    IconRepeat,
    IconSettings,
    IconSun,
    IconTarget,
    IconUpload,
    IconWallet,
    IconX,
} from '../components/Icons';
import { TransactionFormDialog } from '../components/finance/TransactionFormDialog';
import { FlashMessages } from '../components/ui/FlashMessages';

export default function AppLayout({
    children,
    isSettings = false,
}: {
    children: ReactNode;
    isSettings?: boolean;
}) {
    const t = useTrans();
    const { auth, app } = usePage<SharedProps>().props;
    const user = auth.user;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [quickAdd, setQuickAdd] = useState(false);
    const theme = usePrefersTheme();

    const path = usePage().url.split('?')[0];

    const items = [
        { href: '/dashboard', label: t('nav.dashboard'), icon: IconHome },
        { href: '/accounts', label: t('nav.accounts'), icon: IconGrid },
        {
            href: '/transactions',
            label: t('nav.transactions'),
            icon: IconReceipt,
        },
        {
            href: '/receipts/scan',
            label: t('nav.receipt_scan'),
            icon: IconUpload,
        },
        { href: '/budgets', label: t('nav.budgets'), icon: IconTarget },
        { href: '/goals', label: t('nav.goals'), icon: IconTarget },
        { href: '/recurring', label: t('nav.recurring'), icon: IconRepeat },
        { href: '/reports', label: t('nav.reports'), icon: IconChart },
        { href: '/assistant', label: t('nav.assistant'), icon: IconChat },
        { href: '/bills', label: t('nav.bills'), icon: IconCard },
        { href: '/debts', label: t('nav.debts'), icon: IconWallet },
        {
            href: '/notifications',
            label: t('nav.notifications'),
            icon: IconBell,
        },
    ];

    const isActive = (href: string) =>
        path === href || path.startsWith(href + '/');
    const settingsActive = path.startsWith('/settings');

    const logout = useCallback(() => router.post('/logout'), []);

    const initials = (user?.name ?? '')
        .split(' ')
        .map((n) => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

    return (
        <div className="bg-canvas text-ink min-h-dvh transition-colors duration-200">
            <FlashMessages />
            <TransactionFormDialog
                open={quickAdd}
                onClose={() => setQuickAdd(false)}
            />

            {/* Mobile backdrop */}
            {sidebarOpen ? (
                <button
                    className="bg-ink/25 fixed inset-0 z-40 backdrop-blur-[2px] lg:hidden"
                    onClick={() => setSidebarOpen(false)}
                    aria-label={t('common.close_menu')}
                />
            ) : null}

            {/* Sidebar */}
            <aside
                className={`border-line bg-surface fixed inset-y-0 start-0 z-40 flex w-64 flex-col border-e transition-transform duration-200 ${
                    sidebarOpen
                        ? 'translate-x-0'
                        : '-translate-x-full rtl:translate-x-full'
                } lg:translate-x-0 lg:rtl:translate-x-0`}
            >
                {/* Header / Logo */}
                <div className="flex h-16 items-center justify-between px-5">
                    <Brand />
                    <button
                        type="button"
                        className="text-ink-faint hover:bg-surface-strong hover:text-ink rounded-lg p-1.5 transition lg:hidden"
                        onClick={() => setSidebarOpen(false)}
                        aria-label={t('common.close_menu')}
                    >
                        <IconX size={20} />
                    </button>
                </div>

                {/* Navigation */}
                <nav
                    className="mt-2 flex-1 space-y-0.5 overflow-y-auto px-3"
                    aria-label={t('nav.main')}
                >
                    {items.map((item) => {
                        const active = isActive(item.href);
                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                onClick={() => setSidebarOpen(false)}
                                className={`group relative flex items-center gap-3 rounded-[10px] px-3 py-2.5 text-sm font-medium transition ${
                                    active
                                        ? 'bg-accent-soft text-accent'
                                        : 'text-ink hover:bg-surface-soft'
                                }`}
                            >
                                {active ? (
                                    <span className="bg-accent absolute inset-y-2 start-0 w-0.5 rounded-full" />
                                ) : null}
                                <item.icon
                                    size={19}
                                    strokeWidth={active ? 2.2 : 1.8}
                                    className={
                                        active
                                            ? 'text-accent'
                                            : 'text-ink-faint'
                                    }
                                />
                                <span className="truncate">{item.label}</span>
                            </Link>
                        );
                    })}
                </nav>

                {/* Footer / Profile & settings */}
                <div className="border-line border-t p-3">
                    <div className="flex items-center justify-between rounded-xl px-2 py-1.5">
                        <Link
                            href="/settings/appearance"
                            className={`flex items-center gap-2 rounded-lg px-2 py-1 text-[13px] font-medium transition ${
                                settingsActive
                                    ? 'text-accent'
                                    : 'text-ink hover:bg-surface-soft'
                            }`}
                        >
                            <IconSettings
                                size={17}
                                className="text-ink-faint"
                            />
                            {t('nav.settings')}
                        </Link>
                        <button
                            type="button"
                            onClick={theme.toggle}
                            className="text-ink-faint hover:bg-surface-strong hover:text-ink rounded-lg p-2 transition"
                            aria-label={t('common.theme')}
                            title={
                                app.theme === 'dark'
                                    ? 'Light mode'
                                    : 'Dark mode'
                            }
                        >
                            {app.theme === 'dark' ? (
                                <IconSun
                                    size={17}
                                    className="text-amberbrand"
                                />
                            ) : (
                                <IconMoon size={17} className="text-ink-soft" />
                            )}
                        </button>
                    </div>

                    <div className="mt-2 flex items-center gap-3 rounded-xl px-2 py-2">
                        <span className="bg-accent-soft text-accent flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[13px] font-semibold">
                            {initials || 'U'}
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="text-ink truncate text-[13px] font-medium">
                                {user?.name}
                            </p>
                            <p className="text-ink-faint truncate text-[11px]">
                                {user?.email}
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={logout}
                            className="text-ink-faint hover:bg-coral/10 hover:text-coral rounded-lg p-2 transition"
                            aria-label={t('auth.logout')}
                            title={t('auth.logout')}
                        >
                            <IconLogout size={17} />
                        </button>
                    </div>
                </div>
            </aside>

            {/* Mobile top bar */}
            <header className="border-line bg-surface/85 sticky top-0 z-30 flex h-14 items-center justify-between border-b px-4 backdrop-blur-lg lg:hidden">
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => setSidebarOpen(true)}
                        className="text-ink-soft hover:bg-surface-strong rounded-lg p-2 transition"
                        aria-label={t('common.menu')}
                    >
                        <IconMenu size={22} />
                    </button>
                    <Brand size={26} withTagline={false} />
                </div>
                {!isSettings ? (
                    <button
                        type="button"
                        onClick={() => setQuickAdd(true)}
                        className="btn-primary px-3 py-1.5 text-xs font-semibold"
                    >
                        <IconPlus size={15} />
                        {t('common.add')}
                    </button>
                ) : null}
            </header>

            {/* Content */}
            <main className="lg:ps-64">
                <div className="mx-auto w-full max-w-7xl px-4 pt-6 pb-24 sm:px-6 lg:px-8 lg:pb-12">
                    {children}
                </div>
            </main>

            {/* Mobile bottom navigation */}
            <nav
                className="pb-safe border-line bg-surface/95 fixed inset-x-0 bottom-0 z-30 flex items-stretch justify-around border-t px-1 pb-1 backdrop-blur-lg lg:hidden"
                aria-label={t('nav.mobile')}
            >
                {[items[0], items[1]].map((item) => (
                    <Link
                        key={item.href}
                        href={item.href}
                        className={`flex min-w-0 flex-1 flex-col items-center justify-center gap-0.5 py-2 text-[10px] font-medium transition ${
                            isActive(item.href) ? 'text-accent' : 'text-ink'
                        }`}
                    >
                        <item.icon
                            size={21}
                            strokeWidth={isActive(item.href) ? 2.2 : 1.8}
                        />
                        <span className="truncate">{item.label}</span>
                    </Link>
                ))}

                {!isSettings ? (
                    <button
                        type="button"
                        onClick={() => setQuickAdd(true)}
                        className="relative flex flex-col items-center justify-center px-1"
                        aria-label={t('common.add')}
                    >
                        <span className="bg-accent text-accent-contrast shadow-lift ring-canvas -mt-5 flex h-12 w-12 items-center justify-center rounded-full ring-4">
                            <IconPlus size={22} />
                        </span>
                    </button>
                ) : null}

                {[items[2], items[7]].map((item) => (
                    <Link
                        key={item.href}
                        href={item.href}
                        className={`flex min-w-0 flex-1 flex-col items-center justify-center gap-0.5 py-2 text-[10px] font-medium transition ${
                            isActive(item.href) ? 'text-accent' : 'text-ink'
                        }`}
                    >
                        <item.icon
                            size={21}
                            strokeWidth={isActive(item.href) ? 2.2 : 1.8}
                        />
                        <span className="truncate">{item.label}</span>
                    </Link>
                ))}
            </nav>
        </div>
    );
}
