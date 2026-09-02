import { Button, Dialog, Input } from '@/components/ui';
import { useTrans } from '@/lib/translation';
import { useEffect, useState } from 'react';

interface Props {
    open: boolean;
    onClose: () => void;
    onSuccess: () => void;
    title?: string;
}

export function ConfirmPasswordDialog({
    open,
    onClose,
    onSuccess,
    title,
}: Props) {
    const t = useTrans();
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        if (open) {
            setPassword('');
            setError('');
        }
    }, [open]);

    const csrfToken = () =>
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? '';

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);
        setError('');
        fetch('/user/confirm-password', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ password }),
        })
            .then(async (res) => {
                if (res.ok) {
                    onSuccess();
                    onClose();
                    return;
                }
                const data = await res.json().catch(() => ({}));
                setError(data?.errors?.password?.[0] ?? t('auth.password'));
            })
            .catch(() => setError(t('auth.password')))
            .finally(() => setSubmitting(false));
    };

    return (
        <Dialog
            open={open}
            onClose={onClose}
            title={title ?? t('auth.confirm_password')}
            size="sm"
        >
            <form onSubmit={submit} className="space-y-4">
                <p className="text-ink-faint text-sm">
                    {t('settings.confirm_password_hint')}
                </p>
                <Input
                    label={t('auth.password')}
                    type="password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    error={error}
                    autoFocus
                />
                <div className="flex justify-end gap-2 pt-2">
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={onClose}
                        disabled={submitting}
                    >
                        {t('common.cancel')}
                    </Button>
                    <Button type="submit" loading={submitting}>
                        {t('auth.confirm')}
                    </Button>
                </div>
            </form>
        </Dialog>
    );
}
