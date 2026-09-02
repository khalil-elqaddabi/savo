import SettingsLayout from '@/Layouts/SettingsLayout';
import { useAppLock } from '@/components/AppLock/AppLockProvider';
import { IconGlobe, IconLock, IconShield } from '@/components/Icons';
import { ConfirmPasswordDialog } from '@/components/settings/ConfirmPasswordDialog';
import { Badge, Button, Card, Input } from '@/components/ui';
import { useTrans } from '@/lib/translation';
import type { SharedProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

type RecoveryAction = 'show' | 'regenerate';

export default function SettingsSecurity() {
    const t = useTrans();
    const user = usePage<SharedProps>().props.auth.user;
    const [recoveryCodes, setRecoveryCodes] = useState<string[] | null>(null);
    const [loading2fa, setLoading2fa] = useState(false);
    const [loadingRecovery, setLoadingRecovery] = useState(false);
    const [loadingUnlink, setLoadingUnlink] = useState(false);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [pendingAction, setPendingAction] = useState<RecoveryAction | null>(
        null,
    );

    const twoFactorEnabled = Boolean(user?.two_factor_enabled);
    const hasGoogle = Boolean(user?.has_google_link);

    const toggle2fa = () => {
        setLoading2fa(true);
        if (twoFactorEnabled) {
            router.delete('/user/two-factor-authentication', {
                onFinish: () => {
                    setLoading2fa(false);
                    setRecoveryCodes(null);
                },
            });
        } else {
            router.post(
                '/user/two-factor-authentication',
                {},
                { onFinish: () => setLoading2fa(false) },
            );
        }
    };

    const csrfToken = () =>
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? '';

    const runRecoveryAction = async (action: RecoveryAction) => {
        setLoadingRecovery(true);
        try {
            const res = await fetch('/user/two-factor-recovery-codes', {
                method: action === 'regenerate' ? 'POST' : 'GET',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                    ...(action === 'regenerate'
                        ? { 'Content-Type': 'application/json' }
                        : {}),
                },
                body: action === 'regenerate' ? '{}' : undefined,
            });

            if (res.status === 423) {
                setPendingAction(action);
                setConfirmOpen(true);
                return;
            }

            if (action === 'regenerate') {
                const fresh = await fetch('/user/two-factor-recovery-codes', {
                    headers: { Accept: 'application/json' },
                });
                if (fresh.ok) {
                    const codes = await fresh.json();
                    setRecoveryCodes(
                        Array.isArray(codes) && codes.length ? codes : null,
                    );
                }
                return;
            }

            if (!res.ok) return;
            const codes = await res.json();
            setRecoveryCodes(
                Array.isArray(codes) && codes.length ? codes : null,
            );
        } catch {
            /* ignore */
        } finally {
            setLoadingRecovery(false);
        }
    };

    const showRecoveryCodes = () => runRecoveryAction('show');

    const regenerateRecoveryCodes = () => runRecoveryAction('regenerate');

    const onConfirmSuccess = () => {
        setConfirmOpen(false);
        const action = pendingAction;
        setPendingAction(null);
        if (action) runRecoveryAction(action);
    };

    const unlinkGoogle = () => {
        setLoadingUnlink(true);
        router.post(
            '/auth/google/unlink',
            {},
            { onFinish: () => setLoadingUnlink(false) },
        );
    };

    const appLock = useAppLock();

    const [enableForm, setEnableForm] = useState({ pin: '', confirm: '' });
    const [changeForm, setChangeForm] = useState({
        current: '',
        next: '',
        confirm: '',
    });
    const [disablePin, setDisablePin] = useState('');
    const [appLockErrors, setAppLockErrors] = useState<Record<string, string>>(
        {},
    );
    const [appLockNotice, setAppLockNotice] = useState('');
    const [appLockBusy, setAppLockBusy] = useState(false);

    const isValidPin = (pin: string) => /^\d{4,6}$/.test(pin);

    const lockErrorKey = (error?: Error | null) =>
        error?.message === 'lock_crypto_unavailable'
            ? 'settings.app_lock_crypto'
            : 'settings.app_lock_wrong_pin';

    const submitEnable = async () => {
        setAppLockErrors({});
        setAppLockNotice('');
        if (!isValidPin(enableForm.pin)) {
            setAppLockErrors({ pin: t('settings.app_lock_pin_invalid') });
            return;
        }
        if (enableForm.pin !== enableForm.confirm) {
            setAppLockErrors({ confirm: t('settings.app_lock_mismatch') });
            return;
        }
        setAppLockBusy(true);
        const err = await appLock.enable(enableForm.pin);
        setAppLockBusy(false);
        if (err) {
            setAppLockErrors({ pin: t(lockErrorKey(err)) });
            return;
        }
        setEnableForm({ pin: '', confirm: '' });
        setAppLockNotice(t('settings.app_lock_enabled_notice'));
    };

    const submitChange = async () => {
        setAppLockErrors({});
        setAppLockNotice('');
        if (!isValidPin(changeForm.next)) {
            setAppLockErrors({
                next: t('settings.app_lock_pin_invalid'),
            });
            return;
        }
        if (changeForm.next !== changeForm.confirm) {
            setAppLockErrors({ confirm: t('settings.app_lock_mismatch') });
            return;
        }
        setAppLockBusy(true);
        const err = await appLock.change(changeForm.current, changeForm.next);
        setAppLockBusy(false);
        if (err) {
            setAppLockErrors({ current: t(lockErrorKey(err)) });
            return;
        }
        setChangeForm({ current: '', next: '', confirm: '' });
        setAppLockNotice(t('settings.app_lock_changed_notice'));
    };

    const submitDisable = async () => {
        setAppLockErrors({});
        setAppLockNotice('');
        setAppLockBusy(true);
        const ok = await appLock.disable(disablePin);
        setAppLockBusy(false);
        if (!ok) {
            setAppLockErrors({ disable: t('settings.app_lock_wrong_pin') });
            return;
        }
        setDisablePin('');
        setAppLockNotice(t('settings.app_lock_disabled_notice'));
    };

    return (
        <SettingsLayout>
            <div className="space-y-5">
                <Card title={t('settings.two_factor')}>
                    <div className="flex items-start gap-3">
                        <span className="bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl">
                            <IconShield size={20} />
                        </span>
                        <div className="flex-1">
                            <div className="flex items-center gap-2">
                                <p className="text-ink text-sm font-medium dark:text-white">
                                    {t('settings.authenticator_apps')}
                                </p>
                                {twoFactorEnabled ? (
                                    <Badge tone="success">
                                        {t('settings.enabled')}
                                    </Badge>
                                ) : (
                                    <Badge tone="neutral">
                                        {t('settings.disabled')}
                                    </Badge>
                                )}
                            </div>
                            <p className="text-ink-faint mt-1 text-xs">
                                {t('settings.two_factor_hint')}
                            </p>
                            <div className="mt-3 flex flex-wrap gap-2">
                                <Button
                                    onClick={toggle2fa}
                                    loading={loading2fa}
                                >
                                    {twoFactorEnabled
                                        ? t('settings.disable_2fa')
                                        : t('settings.enable_2fa')}
                                </Button>
                                {twoFactorEnabled ? (
                                    <Button
                                        variant="secondary"
                                        onClick={showRecoveryCodes}
                                        loading={
                                            loadingRecovery && !recoveryCodes
                                        }
                                    >
                                        {t('settings.show_recovery_codes')}
                                    </Button>
                                ) : null}
                            </div>
                            {recoveryCodes ? (
                                <div className="bg-surface-strong mt-4 rounded-xl p-4 dark:bg-white/5">
                                    <p className="text-ink-faint mb-2 text-xs font-medium">
                                        {t('settings.recovery_codes')}
                                    </p>
                                    <div className="grid grid-cols-2 gap-2">
                                        {recoveryCodes.map((c) => (
                                            <code
                                                key={c}
                                                className="bg-surface rounded-lg px-2 py-1 text-xs dark:bg-white/10"
                                            >
                                                {c}
                                            </code>
                                        ))}
                                    </div>
                                    <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                                        <p className="text-xs text-amber-600">
                                            {t('settings.recovery_codes_warn')}
                                        </p>
                                        <Button
                                            variant="soft"
                                            size="sm"
                                            onClick={regenerateRecoveryCodes}
                                            loading={
                                                loadingRecovery && recoveryCodes
                                            }
                                        >
                                            {t(
                                                'settings.regenerate_recovery_codes',
                                            )}
                                        </Button>
                                    </div>
                                </div>
                            ) : null}
                        </div>
                    </div>
                </Card>

                <Card title={t('settings.google_login')}>
                    <div className="flex items-start gap-3">
                        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">
                            <IconGlobe size={20} />
                        </span>
                        <div className="flex-1">
                            <div className="flex items-center gap-2">
                                <p className="text-ink text-sm font-medium dark:text-white">
                                    {t('settings.google_account')}
                                </p>
                                {hasGoogle ? (
                                    <Badge tone="success">
                                        {t('settings.linked')}
                                    </Badge>
                                ) : (
                                    <Badge tone="neutral">
                                        {t('settings.not_linked')}
                                    </Badge>
                                )}
                            </div>
                            <p className="text-ink-faint mt-1 text-xs">
                                {t('settings.google_hint')}
                            </p>
                            <div className="mt-3">
                                {hasGoogle ? (
                                    <Button
                                        variant="danger"
                                        onClick={unlinkGoogle}
                                        loading={loadingUnlink}
                                    >
                                        {t('settings.unlink_google')}
                                    </Button>
                                ) : (
                                    <a
                                        href="/auth/google"
                                        className="btn-primary"
                                    >
                                        {t('settings.link_google')}
                                    </a>
                                )}
                            </div>
                        </div>
                    </div>
                </Card>

                <Card title={t('settings.app_lock')}>
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
                        <span className="bg-surface-strong flex h-10 w-10 shrink-0 items-center justify-center rounded-xl dark:bg-white/5">
                            <IconLock size={20} className="text-ink-soft" />
                        </span>
                        <div className="flex-1 space-y-4">
                            <div className="flex items-center gap-2">
                                <p className="text-ink text-sm font-medium dark:text-white">
                                    {t('settings.app_lock_title')}
                                </p>
                                {appLock.isEnabled ? (
                                    <Badge tone="success">
                                        {t('settings.enabled')}
                                    </Badge>
                                ) : (
                                    <Badge tone="neutral">
                                        {t('settings.disabled')}
                                    </Badge>
                                )}
                            </div>
                            <p className="text-ink-faint text-xs">
                                {t('settings.app_lock_hint')}
                            </p>

                            {appLockNotice ? (
                                <p
                                    className="text-brand text-xs font-medium"
                                    role="status"
                                >
                                    {appLockNotice}
                                </p>
                            ) : null}

                            {!appLock.isEnabled ? (
                                <div className="space-y-3">
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <Input
                                            label={t(
                                                'settings.app_lock_new_pin',
                                            )}
                                            type="password"
                                            inputMode="numeric"
                                            autoComplete="new-password"
                                            minLength={4}
                                            maxLength={6}
                                            pattern="[0-9]*"
                                            value={enableForm.pin}
                                            onChange={(e) =>
                                                setEnableForm({
                                                    ...enableForm,
                                                    pin: e.target.value,
                                                })
                                            }
                                            error={appLockErrors.pin}
                                        />
                                        <Input
                                            label={t(
                                                'settings.app_lock_confirm_pin',
                                            )}
                                            type="password"
                                            inputMode="numeric"
                                            autoComplete="new-password"
                                            minLength={4}
                                            maxLength={6}
                                            pattern="[0-9]*"
                                            value={enableForm.confirm}
                                            onChange={(e) =>
                                                setEnableForm({
                                                    ...enableForm,
                                                    confirm: e.target.value,
                                                })
                                            }
                                            error={appLockErrors.confirm}
                                        />
                                    </div>
                                    <Button
                                        onClick={submitEnable}
                                        loading={appLockBusy}
                                        disabled={!enableForm.pin}
                                    >
                                        {t('settings.app_lock_enable')}
                                    </Button>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    <div className="space-y-3">
                                        <div className="grid gap-3 sm:grid-cols-3">
                                            <Input
                                                label={t(
                                                    'settings.app_lock_current_pin',
                                                )}
                                                type="password"
                                                inputMode="numeric"
                                                autoComplete="current-password"
                                                maxLength={6}
                                                pattern="[0-9]*"
                                                value={changeForm.current}
                                                onChange={(e) =>
                                                    setChangeForm({
                                                        ...changeForm,
                                                        current: e.target.value,
                                                    })
                                                }
                                            />
                                            <Input
                                                label={t(
                                                    'settings.app_lock_new_pin',
                                                )}
                                                type="password"
                                                inputMode="numeric"
                                                autoComplete="new-password"
                                                minLength={4}
                                                maxLength={6}
                                                pattern="[0-9]*"
                                                value={changeForm.next}
                                                onChange={(e) =>
                                                    setChangeForm({
                                                        ...changeForm,
                                                        next: e.target.value,
                                                    })
                                                }
                                                error={appLockErrors.next}
                                            />
                                            <Input
                                                label={t(
                                                    'settings.app_lock_confirm_pin',
                                                )}
                                                type="password"
                                                inputMode="numeric"
                                                autoComplete="new-password"
                                                minLength={4}
                                                maxLength={6}
                                                pattern="[0-9]*"
                                                value={changeForm.confirm}
                                                onChange={(e) =>
                                                    setChangeForm({
                                                        ...changeForm,
                                                        confirm: e.target.value,
                                                    })
                                                }
                                                error={appLockErrors.confirm}
                                            />
                                        </div>
                                        <Button
                                            variant="secondary"
                                            onClick={submitChange}
                                            loading={appLockBusy}
                                            disabled={!changeForm.current}
                                        >
                                            {t('settings.app_lock_change')}
                                        </Button>
                                    </div>

                                    <div className="border-ink-faint/20 border-t pt-4">
                                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                                            <Input
                                                label={t(
                                                    'settings.app_lock_disable_pin',
                                                )}
                                                type="password"
                                                inputMode="numeric"
                                                autoComplete="current-password"
                                                maxLength={6}
                                                pattern="[0-9]*"
                                                value={disablePin}
                                                onChange={(e) =>
                                                    setDisablePin(
                                                        e.target.value,
                                                    )
                                                }
                                                error={appLockErrors.disable}
                                                className="sm:max-w-[220px]"
                                            />
                                            <Button
                                                variant="danger"
                                                onClick={submitDisable}
                                                loading={appLockBusy}
                                                disabled={!disablePin}
                                            >
                                                {t('settings.app_lock_disable')}
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </Card>
            </div>
            <ConfirmPasswordDialog
                open={confirmOpen}
                onClose={() => {
                    setConfirmOpen(false);
                    setPendingAction(null);
                }}
                onSuccess={onConfirmSuccess}
                title={t('auth.confirm_password')}
            />
        </SettingsLayout>
    );
}
