import { Button, Input } from '@/components/ui';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTrans } from '@/lib/translation';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function ResetPassword() {
    const t = useTrans();
    const { errors, token, email: routeEmail } = usePage<any>().props;
    const [form, setForm] = useState({
        email: routeEmail ?? '',
        password: '',
        password_confirmation: '',
    });
    const [submitting, setSubmitting] = useState(false);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);
        router.post(
            '/reset-password',
            { ...form, token },
            {
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <GuestLayout>
            <div className="card animate-fade-up space-y-6">
                <div className="text-center">
                    <h1 className="text-ink text-2xl font-bold dark:text-white">
                        {t('auth.reset_password')}
                    </h1>
                    <p className="text-ink-faint mt-1 text-sm">
                        {t('auth.reset_hint')}
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <Input
                        label={t('auth.email')}
                        type="email"
                        value={form.email}
                        onChange={(e) =>
                            setForm({ ...form, email: e.target.value })
                        }
                        error={errors?.email}
                        autoFocus
                    />
                    <Input
                        label={t('auth.password')}
                        type="password"
                        value={form.password}
                        onChange={(e) =>
                            setForm({ ...form, password: e.target.value })
                        }
                        error={errors?.password}
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
                    />
                    <Button type="submit" fullWidth loading={submitting}>
                        {t('auth.reset_password')}
                    </Button>
                </form>
            </div>
        </GuestLayout>
    );
}
