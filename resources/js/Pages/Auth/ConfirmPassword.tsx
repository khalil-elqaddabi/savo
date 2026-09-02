import { Button, Input } from '@/components/ui';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTrans } from '@/lib/translation';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function ConfirmPassword() {
    const t = useTrans();
    const { errors } = usePage<any>().props;
    const [password, setPassword] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);
        router.post(
            '/user/confirm-password',
            { password },
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
                        {t('auth.confirm_password')}
                    </h1>
                    <p className="text-ink-faint mt-1 text-sm">
                        {t('auth.confirm_hint')}
                    </p>
                </div>
                <form onSubmit={submit} className="space-y-4">
                    <Input
                        label={t('auth.password')}
                        type="password"
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        error={errors?.password}
                        autoFocus
                    />
                    <Button type="submit" fullWidth loading={submitting}>
                        {t('auth.confirm')}
                    </Button>
                </form>
            </div>
        </GuestLayout>
    );
}
