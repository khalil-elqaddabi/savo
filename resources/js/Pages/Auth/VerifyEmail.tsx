import { Button } from '@/components/ui';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTrans } from '@/lib/translation';
import type { SharedProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function VerifyEmail() {
    const t = useTrans();
    const { auth } = usePage<SharedProps>().props;
    const [submitting, setSubmitting] = useState(false);

    const resend = () => {
        setSubmitting(true);
        router.post(
            '/email/verification-notification',
            {},
            { onFinish: () => setSubmitting(false) },
        );
    };

    const emailVerified = Boolean(auth.user?.email_verified_at);

    return (
        <GuestLayout>
            <div className="card animate-fade-up space-y-6 text-center">
                <div className="bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300 mx-auto flex h-14 w-14 items-center justify-center rounded-full">
                    <svg
                        width="26"
                        height="26"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                    >
                        <rect x="3" y="5" width="18" height="14" rx="2" />
                        <path d="m3 7 9 6 9-6" />
                    </svg>
                </div>
                <div>
                    <h1 className="text-ink text-2xl font-bold dark:text-white">
                        {t('auth.verify_email')}
                    </h1>
                    <p className="text-ink-faint mt-2 text-sm">
                        {t('auth.verify_hint')}
                    </p>
                </div>

                {emailVerified ? (
                    <p className="bg-mint/10 text-mint dark:text-mint rounded-xl px-4 py-3 text-sm">
                        {t('auth.email_verified')}
                    </p>
                ) : (
                    <Button onClick={resend} loading={submitting} fullWidth>
                        {t('auth.resend_link')}
                    </Button>
                )}

                <Link
                    href="/login"
                    className="text-brand-600 dark:text-brand-400 block text-sm font-semibold hover:underline"
                    onClick={(e) => {
                        e.preventDefault();
                        router.post('/logout');
                    }}
                >
                    {t('auth.logout')}
                </Link>
            </div>
        </GuestLayout>
    );
}
