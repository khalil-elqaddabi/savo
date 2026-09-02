import { IconShield } from '@/components/Icons';
import { Button, Input } from '@/components/ui';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTrans } from '@/lib/translation';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function TwoFactorChallenge() {
    const t = useTrans();
    const { errors } = usePage<any>().props;
    const [code, setCode] = useState('');
    const [recovery, setRecovery] = useState('');
    const [mode, setMode] = useState<'code' | 'recovery'>('code');
    const [submitting, setSubmitting] = useState(false);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);

        const payload =
            mode === 'code'
                ? { code: code.replace(/\s/g, '') }
                : { recovery_code: recovery };

        router.post('/two-factor-challenge', payload, {
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <GuestLayout>
            <div className="card animate-fade-up space-y-6">
                <div className="text-center">
                    <div className="bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300 mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full">
                        <IconShield size={24} />
                    </div>
                    <h1 className="text-ink text-2xl font-bold dark:text-white">
                        {t('auth.two_factor')}
                    </h1>
                    <p className="text-ink-faint mt-1 text-sm">
                        {mode === 'code'
                            ? t('auth.two_factor_hint')
                            : t('auth.recovery_code_hint')}
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    {mode === 'code' ? (
                        <Input
                            label={t('auth.verification_code')}
                            value={code}
                            onChange={(e) => setCode(e.target.value)}
                            error={errors?.code}
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            autoFocus
                            placeholder="000000"
                        />
                    ) : (
                        <Input
                            label={t('auth.recovery_code')}
                            value={recovery}
                            onChange={(e) => setRecovery(e.target.value)}
                            error={errors?.recovery_code}
                            autoFocus
                        />
                    )}

                    <Button type="submit" fullWidth loading={submitting}>
                        {t('auth.continue')}
                    </Button>
                </form>

                <div className="text-center text-xs">
                    {mode === 'code' ? (
                        <button
                            type="button"
                            onClick={() => setMode('recovery')}
                            className="text-ink-faint hover:text-ink dark:hover:text-white"
                        >
                            {t('auth.use_recovery_code')}
                        </button>
                    ) : (
                        <button
                            type="button"
                            onClick={() => setMode('code')}
                            className="text-ink-faint hover:text-ink dark:hover:text-white"
                        >
                            {t('auth.use_code')}
                        </button>
                    )}
                </div>
            </div>
        </GuestLayout>
    );
}
