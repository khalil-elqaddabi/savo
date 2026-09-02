import { Button, Input } from '@/components/ui';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTrans } from '@/lib/translation';
import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function ForgotPassword() {
    const t = useTrans();
    const { errors, status } = usePage<any>().props;
    const [email, setEmail] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);
        router.post(
            '/forgot-password',
            { email },
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
                        {t('auth.forgot_password')}
                    </h1>
                    <p className="text-ink-faint mt-1 text-sm">
                        {t('auth.forgot_hint')}
                    </p>
                </div>

                {status || (typeof status === 'string' ? status : '') ? (
                    <p className="bg-mint/10 text-mint rounded-xl px-4 py-3 text-sm dark:text-emerald-300">
                        {status}
                    </p>
                ) : null}

                <form onSubmit={submit} className="space-y-4">
                    <Input
                        label={t('auth.email')}
                        type="email"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        error={errors?.email}
                        autoFocus
                    />
                    <Button type="submit" fullWidth loading={submitting}>
                        {t('auth.send_reset_link')}
                    </Button>
                </form>

                <p className="text-center text-sm">
                    <Link
                        href="/login"
                        className="text-brand-600 dark:text-brand-400 font-semibold hover:underline"
                    >
                        {t('auth.back_to_login')}
                    </Link>
                </p>
            </div>
        </GuestLayout>
    );
}
