import SettingsLayout from '@/Layouts/SettingsLayout';
import { IconDownload, IconRefresh, IconUpload } from '@/components/Icons';
import { Button, Card, Dialog, Input } from '@/components/ui';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { useTrans } from '@/lib/translation';
import type { SharedProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';

export default function SettingsData() {
    const t = useTrans();
    const { auth } = usePage<SharedProps>().props;
    const isGoogleAuth = !!auth?.user?.has_google_link;
    const fileRef = useRef<HTMLInputElement>(null);
    const [importing, setImporting] = useState(false);
    const [importError, setImportError] = useState('');
    const [resetOpen, setResetOpen] = useState(false);
    const [resetConfirmOpen, setResetConfirmOpen] = useState(false);
    const [password, setPassword] = useState('');
    const [passwordError, setPasswordError] = useState('');
    const [resetting, setResetting] = useState(false);

    const submitImport = () => {
        const file = fileRef.current?.files?.[0];
        if (!file) {
            setImportError(t('settings.data_import_required'));
            return;
        }
        setImporting(true);
        setImportError('');
        const form = new FormData();
        form.append('file', file);
        router.post('/data/import', form, {
            forceFormData: true,
            preserveScroll: true,
            onError: (e) => {
                setImporting(false);
                setImportError(e.file ?? t('settings.data_import_error'));
            },
            onFinish: () => setImporting(false),
        });
    };

    const openReset = () => {
        setPassword('');
        setPasswordError('');
        if (isGoogleAuth) {
            setResetConfirmOpen(true);
            return;
        }
        setResetOpen(true);
    };

    const submitReset = () => {
        setResetting(true);
        setPasswordError('');
        const data = isGoogleAuth ? {} : { current_password: password };
        router.post('/data/reset', data, {
            preserveScroll: true,
            onError: (e) => {
                setResetting(false);
                setPasswordError(
                    e.current_password ?? t('settings.data_reset_error'),
                );
            },
            onSuccess: () => router.reload(),
            onFinish: () => setResetting(false),
        });
    };

    return (
        <SettingsLayout>
            <div className="space-y-5">
                <Card
                    title={t('settings.data_export')}
                    subtitle={t('settings.data_export_hint')}
                >
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-ink-faint text-sm">
                            {t('settings.data_export_desc')}
                        </p>
                        <a
                            href="/data/export"
                            className="btn-secondary px-4 py-2.5 text-sm"
                        >
                            <IconDownload size={16} />
                            {t('settings.data_export_action')}
                        </a>
                    </div>
                </Card>

                <Card
                    title={t('settings.data_import')}
                    subtitle={t('settings.data_import_hint')}
                >
                    <div className="space-y-3">
                        <p className="text-ink-faint text-sm">
                            {t('settings.data_import_desc')}
                        </p>
                        <input
                            ref={fileRef}
                            type="file"
                            accept=".csv,.txt,text/csv"
                            className="file:btn-soft text-ink-faint text-sm file:mr-3"
                        />
                        {importError ? (
                            <p className="text-coral text-xs">{importError}</p>
                        ) : null}
                        <div className="flex justify-end">
                            <Button
                                onClick={submitImport}
                                loading={importing}
                                variant="secondary"
                            >
                                <IconUpload size={16} />
                                {t('settings.data_import_action')}
                            </Button>
                        </div>
                    </div>
                </Card>

                <Card
                    title={t('settings.data_reset')}
                    className="border-red-200 dark:border-red-500/30"
                >
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <p className="text-ink text-sm font-medium dark:text-white">
                                {t('settings.data_reset_desc')}
                            </p>
                            <p className="text-ink-faint mt-1 text-xs">
                                {t('settings.data_reset_hint')}
                            </p>
                        </div>
                        <Button
                            variant="danger"
                            onClick={openReset}
                            className="shrink-0"
                        >
                            <IconRefresh size={15} />
                            {t('settings.data_reset_action')}
                        </Button>
                    </div>
                </Card>
            </div>

            <Dialog
                open={resetOpen}
                onClose={() => setResetOpen(false)}
                title={t('settings.data_reset')}
            >
                <div className="space-y-4">
                    <p className="text-ink-faint text-sm">
                        {t('settings.data_reset_confirm_message')}
                    </p>
                    <Input
                        label={t('auth.password')}
                        type="password"
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        error={passwordError}
                    />
                    <div className="flex justify-end gap-2 pt-1">
                        <Button
                            variant="secondary"
                            onClick={() => setResetOpen(false)}
                            disabled={resetting}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button
                            variant="danger"
                            onClick={() => {
                                setResetOpen(false);
                                setResetConfirmOpen(true);
                            }}
                            disabled={!password || resetting}
                        >
                            {t('common.next')}
                        </Button>
                    </div>
                </div>
            </Dialog>

            <ConfirmDialog
                open={resetConfirmOpen}
                onClose={() => setResetConfirmOpen(false)}
                onConfirm={submitReset}
                title={t('settings.data_reset')}
                message={
                    isGoogleAuth
                        ? t('settings.data_reset_oauth_confirm')
                        : passwordError
                          ? `${t('settings.data_reset_final_warning')}\n\n${passwordError}`
                          : t('settings.data_reset_final_warning')
                }
                confirmLabel={t('settings.data_reset_action')}
                loading={resetting}
            />
        </SettingsLayout>
    );
}
