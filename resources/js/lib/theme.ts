import type { SharedProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

function defaultThemeBySystem(): 'light' | 'dark' {
    if (
        typeof window !== 'undefined' &&
        typeof window.matchMedia === 'function' &&
        window.matchMedia('(prefers-color-scheme: light)').matches
    ) {
        return 'light';
    }
    return 'dark';
}

/**
 * Theme management. Persists choice to the server (per-user) and toggles the
 * `dark` and `light` classes on <html> so Tailwind's dark/light variables apply.
 */
export function applyThemeClass(theme: 'light' | 'dark') {
    if (typeof document === 'undefined') return;
    const isDark = theme === 'dark';
    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.classList.toggle('light', !isDark);
    try {
        localStorage.setItem('savo_theme', theme);
    } catch {
        // ignore storage errors
    }
}

function readStoredTheme(): 'light' | 'dark' | null {
    try {
        const stored = localStorage.getItem('savo_theme');
        if (stored === 'light' || stored === 'dark') return stored;
    } catch {
        // ignore storage errors
    }
    return null;
}

export function usePrefersTheme() {
    const page = usePage<SharedProps>();
    const { auth, app } = page.props;
    const isGuest = !auth?.user;
    const serverTheme = (app.theme as 'light' | 'dark') ?? 'dark';

    const [theme, setThemeState] = useState<'light' | 'dark'>(() => {
        // Guests have no persisted per-user theme, so honor a locally chosen
        // theme (or system preference) instead of always defaulting to dark.
        if (isGuest) {
            return readStoredTheme() ?? defaultThemeBySystem();
        }
        return serverTheme;
    });

    useEffect(() => {
        applyThemeClass(theme);
    }, [theme]);

    const setTheme = useCallback(
        (next: 'light' | 'dark') => {
            applyThemeClass(next);
            setThemeState(next);
            if (!isGuest) {
                router.post(
                    '/preferences/theme',
                    { theme: next },
                    { preserveScroll: true },
                );
            }
        },
        [isGuest],
    );

    const toggle = useCallback(() => {
        setTheme(theme === 'dark' ? 'light' : 'dark');
    }, [theme, setTheme]);

    return { theme, setTheme, toggle };
}

/** Apply stored theme on boot */
export function initTheme(theme?: string, isGuest = true) {
    let t: 'light' | 'dark' =
        theme === 'light' || theme === 'dark' ? theme : 'dark';
    if (isGuest) {
        t = readStoredTheme() ?? defaultThemeBySystem();
    }
    applyThemeClass(t);
}
