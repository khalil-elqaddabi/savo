import { Button, Input } from '@/components/ui';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTrans } from '@/lib/translation';
import type { SharedProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function Register() {
    const t = useTrans();
    const { errors, app } = usePage<SharedProps>().props as any;
    const [form, setForm] = useState({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        locale: app.locale,
    });
    const [submitting, setSubmitting] = useState(false);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);
        router.post('/register', form, {
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <GuestLayout>
            <div className="card animate-fade-up space-y-6">
                <div className="text-center">
                    <h1 className="text-ink text-2xl font-bold dark:text-white">
                        {t('auth.create_account')}
                    </h1>
                    <p className="text-ink-faint mt-1 text-sm">
                        {t('auth.create_account_hint')}
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <Input
                        label={t('auth.name')}
                        value={form.name}
                        onChange={(e) =>
                            setForm({ ...form, name: e.target.value })
                        }
                        error={errors?.name}
                        autoFocus
                    />
                    <Input
                        label={t('auth.email')}
                        type="email"
                        value={form.email}
                        onChange={(e) =>
                            setForm({ ...form, email: e.target.value })
                        }
                        error={errors?.email}
                        placeholder={t('auth.email_placeholder')}
                    />
                    <Input
                        label={t('auth.password')}
                        type="password"
                        value={form.password}
                        onChange={(e) =>
                            setForm({ ...form, password: e.target.value })
                        }
                        error={errors?.password}
                        autoComplete="new-password"
                    />
                    <Input
                        label={t('auth.confirm_password')}
                        type="password"
                        value={form.password_confirmation}
                        onChange={(e) =>
                            setForm({
                                ...form,
                                password_confirmation: e.target.value,
                            })
                        }
                        error={errors?.password_confirmation}
                        autoComplete="new-password"
                    />

                    <Button type="submit" fullWidth loading={submitting}>
                        {t('auth.register')}
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
                    className="btn-secondary border-line bg-surface w-full justify-center rounded-xl border py-2.5 dark:border-white/10 dark:bg-white/[0.03]"
                >
                    Google
                </a>

                <p className="text-ink-faint text-center text-sm">
                    {t('auth.have_account')}{' '}
                    <Link
                        href="/login"
                        className="text-brand-600 dark:text-brand-400 font-semibold hover:underline"
                    >
                        {t('auth.login')}
                    </Link>
                </p>
            </div>
        </GuestLayout>
    );
}
