import { createEntry, verifyPin, type StoredLock } from '@/lib/applock-crypto';
import { useTrans } from '@/lib/translation';
import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';

const STORAGE_KEY = 'savo.applock';

/**
 * Client-side app lock (PIN) helper built on the Web Crypto API.
 *
 * The PIN is never stored as plain text and never logged or emitted to
 * console/URLs: only a salted SHA-256 hash is persisted in localStorage. The
 * 'unlocked' state lives in React memory only, so a full page reload or a new
 * session re-locks the app, while normal in-app (Inertia) navigation stays
 * unlocked. This is a soft lock against casual/device access — it is not a
 * replacement for the account authentication system.
 */

interface AppLockContextValue {
    loading: boolean;
    isEnabled: boolean;
    isLocked: boolean;
    lock: () => void;
    unlock: (pin: string) => Promise<boolean>;
    enable: (pin: string) => Promise<Error | null>;
    disable: (pin: string) => Promise<boolean>;
    change: (current: string, next: string) => Promise<Error | null>;
}

const AppLockContext = createContext<AppLockContextValue | null>(null);

function readStored(): StoredLock | null {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        if (!raw) return null;
        const parsed = JSON.parse(raw) as StoredLock;
        if (!parsed.salt || !parsed.hash) return null;
        return parsed;
    } catch {
        return null;
    }
}

async function persist(pin: string): Promise<StoredLock> {
    const entry = await createEntry(pin);
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(entry));
    return entry;
}

export function AppLockProvider({ children }: { children: ReactNode }) {
    const [loading, setLoading] = useState(true);
    const [isEnabled, setIsEnabled] = useState(false);
    const [unlocked, setUnlocked] = useState(false);

    useEffect(() => {
        setIsEnabled(readStored() !== null);
        setLoading(false);
    }, []);

    const lock = useCallback(() => {
        setUnlocked(false);
    }, []);

    const unlock = useCallback(async (pin: string): Promise<boolean> => {
        const stored = readStored();
        if (!stored) return false;
        const ok = await verifyPin(stored, pin);
        if (ok) setUnlocked(true);
        return ok;
    }, []);

    const enable = useCallback(async (pin: string): Promise<Error | null> => {
        try {
            await persist(pin);
            setIsEnabled(true);
            setUnlocked(true);
            return null;
        } catch {
            return new Error('lock_crypto_unavailable');
        }
    }, []);

    const disable = useCallback(async (pin: string): Promise<boolean> => {
        const stored = readStored();
        if (!stored) return false;
        const ok = await verifyPin(stored, pin);
        if (ok) {
            try {
                window.localStorage.removeItem(STORAGE_KEY);
                setIsEnabled(false);
                setUnlocked(false);
            } catch {
                return false;
            }
        }
        return ok;
    }, []);

    const change = useCallback(
        async (current: string, next: string): Promise<Error | null> => {
            const stored = readStored();
            if (!stored) return new Error('lock_wrong_pin');
            if (!(await verifyPin(stored, current))) {
                return new Error('lock_wrong_pin');
            }
            try {
                await persist(next);
                return null;
            } catch {
                return new Error('lock_crypto_unavailable');
            }
        },
        [],
    );

    const isLocked = isEnabled && !unlocked;

    const value = useMemo<AppLockContextValue>(
        () => ({
            loading,
            isEnabled,
            isLocked,
            lock,
            unlock,
            enable,
            disable,
            change,
        }),
        [loading, isEnabled, isLocked, lock, unlock, enable, disable, change],
    );

    if (loading) {
        return <div className="bg-app min-h-screen" aria-hidden="true" />;
    }

    return (
        <AppLockContext.Provider value={value}>
            {isLocked ? <LockScreen onUnlock={unlock} /> : children}
        </AppLockContext.Provider>
    );
}

export function useAppLock(): AppLockContextValue {
    const ctx = useContext(AppLockContext);
    if (!ctx) {
        throw new Error('useAppLock must be used within AppLockProvider');
    }
    return ctx;
}

function LockScreen({
    onUnlock,
}: {
    onUnlock: (pin: string) => Promise<boolean>;
}) {
    const t = useTrans();
    const [pin, setPin] = useState('');
    const [error, setError] = useState(false);
    const [checking, setChecking] = useState(false);

    const submit = async (value: string) => {
        if (checking || value.length === 0) return;
        setChecking(true);
        setError(false);
        const ok = await onUnlock(value);
        setChecking(false);
        if (!ok) {
            setError(true);
            setPin('');
        }
    };

    const pressKey = (key: string) => {
        if (checking) return;
        setError(false);
        setPin((prev) => {
            const next = prev.length < 6 ? prev + key : prev;
            if (next.length === 6) {
                void submit(next);
            }
            return next;
        });
    };

    const backspace = () => {
        if (checking) return;
        setError(false);
        setPin((prev) => prev.slice(0, -1));
    };

    const digits = ['1', '2', '3', '4', '5', '6', '7', '8', '9'];

    return (
        <div className="bg-app flex min-h-screen items-center justify-center px-4">
            <div className="card w-full max-w-sm p-8 text-center">
                <div className="bg-brand/10 text-brand mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl">
                    <svg
                        width="26"
                        height="26"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        aria-hidden="true"
                    >
                        <rect
                            x="3"
                            y="11"
                            width="18"
                            height="11"
                            rx="2"
                            ry="2"
                        />
                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                    </svg>
                </div>
                <h1 className="text-ink text-xl font-bold dark:text-white">
                    {t('lock.title') || 'App Locked'}
                </h1>
                <p className="text-ink-faint mt-1 text-sm">
                    {t('lock.hint') || 'Enter your PIN to continue'}
                </p>

                <div
                    className="mt-6 flex justify-center gap-3"
                    aria-label={t('lock.pin_label') || 'PIN'}
                >
                    {Array.from({ length: 4 }).map((_, i) => (
                        <span
                            key={i}
                            className={`h-3.5 w-3.5 rounded-full transition-colors ${
                                i < pin.length
                                    ? 'bg-brand'
                                    : 'bg-surface-strong dark:bg-white/10'
                            }`}
                            aria-hidden="true"
                        />
                    ))}
                </div>

                {error ? (
                    <p className="text-coral mt-3 text-sm" role="alert">
                        {t('lock.error') || 'Incorrect PIN. Try again.'}
                    </p>
                ) : null}

                <div className="mx-auto mt-6 grid max-w-[220px] grid-cols-3 gap-3">
                    {digits.map((d) => (
                        <button
                            key={d}
                            type="button"
                            onClick={() => pressKey(d)}
                            disabled={checking}
                            className="btn-secondary h-14 rounded-xl text-lg font-bold"
                            aria-label={d}
                        >
                            {d}
                        </button>
                    ))}
                    <div />
                    <button
                        type="button"
                        onClick={() => pressKey('0')}
                        disabled={checking}
                        className="btn-secondary h-14 rounded-xl text-lg font-bold"
                        aria-label="0"
                    >
                        0
                    </button>
                    <button
                        type="button"
                        onClick={backspace}
                        disabled={checking}
                        className="btn-soft text-ink-faint h-14 rounded-xl text-lg"
                        aria-label={t('lock.backspace') || 'Backspace'}
                    >
                        ←
                    </button>
                </div>
            </div>
        </div>
    );
}
