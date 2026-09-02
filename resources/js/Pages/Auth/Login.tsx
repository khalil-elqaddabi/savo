import { Button, Input } from '@/components/ui';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTrans } from '@/lib/translation';
import type { SharedProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function Login() {
    const t = useTrans();
    const { errors: serverErrors, auth } = usePage<SharedProps>().props as any;
    const [form, setForm] = useState({
        email: '',
        password: '',
        remember: true,
    });
    const [submitting, setSubmitting] = useState(false);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);
        router.post('/login', form, {
            onFinish: () => setSubmitting(false),
        });
    };

    const twoFactor = (auth as any)?.two_factor_challenge;

    return (
        <GuestLayout>
            <div className="card animate-fade-up space-y-6">
                <div className="text-center">
                    <h1 className="text-ink text-2xl font-bold dark:text-white">
                        {t('auth.login')}
                    </h1>
                    <p className="text-ink-faint mt-1 text-sm">
                        {t('auth.welcome_back')}
                    </p>
                </div>

                {twoFactor ? (
                    <p className="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                        {t('auth.two_factor_required')}
                    </p>
                ) : null}

                <form onSubmit={submit} className="space-y-4">
                    <Input
                        label={t('auth.email')}
                        type="email"
                        value={form.email}
                        onChange={(e) =>
                            setForm({ ...form, email: e.target.value })
                        }
                        placeholder={t('auth.email_placeholder')}
                        error={serverErrors?.email}
                        autoComplete="email"
                        autoFocus
                    />
                    <div>
                        <Input
                            label={t('auth.password')}
                            type="password"
                            value={form.password}
                            onChange={(e) =>
                                setForm({ ...form, password: e.target.value })
                            }
                            error={serverErrors?.password}
                            autoComplete="current-password"
                        />
                        <div className="mt-2 flex items-center justify-between">
                            <label className="text-ink-soft flex items-center gap-2 text-xs dark:text-white/60">
                                <input
                                    type="checkbox"
                                    checked={form.remember}
                                    onChange={(e) =>
                                        setForm({
                                            ...form,
                                            remember: e.target.checked,
                                        })
                                    }
                                    className="border-line text-brand-600 focus:ring-brand-500/40 rounded"
                                />
                                {t('auth.remember_me')}
                            </label>
                            <Link
                                href="/forgot-password"
                                className="text-brand-600 dark:text-brand-400 text-xs font-medium hover:underline"
                            >
                                {t('auth.forgot_password')}
                            </Link>
                        </div>
                    </div>

                    <Button type="submit" fullWidth loading={submitting}>
                        {t('auth.login')}
                    </Button>
                </form>

                <div className="relative">
                    <div className="absolute inset-0 flex items-center">
                        <div className="border-line w-full border-t dark:border-white/10" />
                    </div>
                    <div className="relative flex justify-center">
                        <span className="bg-surface text-ink-faint px-3 text-xs dark:bg-[#111a2c]">
                            {t('auth.or_continue_with')}
                        </span>
                    </div>
                </div>

                <a
                    href="/auth/google"
                    className="btn-secondary border-line bg-surface w-full rounded-xl border py-2.5 dark:border-white/10 dark:bg-white/[0.03]"
                >
                    <svg width="18" height="18" viewBox="0 0 48 48">
                        <path
                            fill="#FFC107"
                            d="M43.6 20.1H42V20H24v8h11.3c-1.6 4.7-6.1 8-11.3 8-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3l5.7-5.7C33.7 5.2 29.1 3 24 3 12.4 3 3 12.4 3 24s9.4 21 21 21 21-9.4 21-21c0-1.3-.1-2.7-.4-3.9z"
                        />
                        <path
                            fill="#FF3D00"
                            d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.9 1.2 8 3l5.7-5.7C33.7 5.2 29.1 3 24 3 16.3 3 9.7 7.1 6.3 14.7z"
                        />
                        <path
                            fill="#4CAF50"
                            d="M24 45c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 36.1 26.7 37 24 37c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 40.6 16.2 45 24 45z"
                        />
                        <path
                            fill="#1976D2"
                            d="M43.6 20.1H42V20H24v8h11.3c-.8 2.3-2.2 4.3-4.1 5.7l6.2 5.2C40.8 36.3 45 30.8 45 24c0-1.3-.1-2.7-.4-3.9z"
                        />
                    </svg>
                    Google
                </a>

                <p className="text-ink-faint text-center text-sm">
                    {t('auth.no_account')}{' '}
                    <Link
                        href="/register"
                        className="text-brand-600 dark:text-brand-400 font-semibold hover:underline"
                    >
                        {t('auth.register')}
                    </Link>
                </p>
            </div>
        </GuestLayout>
    );
}
