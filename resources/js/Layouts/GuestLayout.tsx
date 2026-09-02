import { usePrefersTheme } from '@/lib/theme';
import { useTrans } from '@/lib/translation';
import type { SharedProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { BrandMark } from '../components/Brand';
import { IconMoon, IconSun } from '../components/Icons';

export default function GuestLayout({ children }: { children: ReactNode }) {
    const t = useTrans();
    const { app } = usePage<SharedProps>().props;
    const theme = usePrefersTheme();

    const switchLocale = (locale: string) => {
        router.post(
            '/preferences/language',
            { locale },
            { preserveScroll: true },
        );
    };

    return (
        <div className="bg-canvas text-ink relative flex min-h-dvh flex-col transition-colors duration-200">
            <header className="absolute inset-x-0 top-0 z-10 flex items-center justify-between px-5 py-5 sm:px-10">
                <a href="/" className="flex items-center gap-2.5">
                    <BrandMark size={34} />
                    <span className="text-[18px] font-semibold tracking-tight">
                        Savo
                    </span>
                </a>

                <div className="flex items-center gap-2">
                    <div className="border-line bg-surface-elevated shadow-card flex items-center rounded-full border p-0.5">
                        {app.supportedLocales.map((l) => (
                            <button
                                key={l.code}
                                type="button"
                                onClick={() => switchLocale(l.code)}
                                className={`rounded-full px-3 py-1 text-xs font-semibold uppercase transition ${
                                    app.locale === l.code
                                        ? 'bg-accent text-accent-contrast'
                                        : 'text-ink-faint hover:text-ink'
                                }`}
                            >
                                {l.code}
                            </button>
                        ))}
                    </div>
                    <button
                        type="button"
                        onClick={theme.toggle}
                        className="border-line bg-surface-elevated text-ink-soft shadow-card hover:text-ink rounded-full border p-2.5 transition"
                        aria-label={t('common.theme')}
                        title={
                            app.theme === 'dark' ? 'Light mode' : 'Dark mode'
                        }
                    >
                        {theme.theme === 'dark' ? (
                            <IconSun size={18} className="text-amberbrand" />
                        ) : (
                            <IconMoon size={18} className="text-ink-soft" />
                        )}
                    </button>
                </div>
            </header>

            <main className="relative z-10 flex flex-1 items-center justify-center px-5 py-24 sm:px-8">
                <div className="w-full max-w-[400px]">{children}</div>
            </main>
        </div>
    );
}
