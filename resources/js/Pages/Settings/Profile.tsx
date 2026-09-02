import SettingsLayout from '@/Layouts/SettingsLayout';
import { Button, Card, Input } from '@/components/ui';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { useTrans } from '@/lib/translation';
import type { SharedProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function SettingsProfile() {
    const t = useTrans();
    const user = usePage<SharedProps>().props.auth.user;
    const errors = (usePage<any>().props.errors || {}) as Record<
        string,
        string
    >;

    const [profile, setProfile] = useState({
        name: user?.name ?? '',
        email: user?.email ?? '',
    });
    const [password, setPassword] = useState({
        current_password: '',
        password: '',
        password_confirmation: '',
    });
    const [savingProfile, setSavingProfile] = useState(false);
    const [savingPassword, setSavingPassword] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);

    const saveProfile = () => {
        setSavingProfile(true);
        router.put('/user/profile-information', profile, {
            onFinish: () => setSavingProfile(false),
        });
    };

    const savePassword = () => {
        setSavingPassword(true);
        router.put('/user/password', password, {
            onFinish: () => {
                setSavingPassword(false);
                setPassword({
                    current_password: '',
                    password: '',
                    password_confirmation: '',
                });
            },
        });
    };

    return (
        <SettingsLayout>
            <div className="space-y-5">
                <Card
                    title={t('settings.profile_info')}
                    subtitle={t('settings.profile_info_hint')}
                >
                    <div className="space-y-4">
                        <Input
                            label={t('settings.name')}
                            value={profile.name}
                            onChange={(e) =>
                                setProfile({ ...profile, name: e.target.value })
                            }
                            error={errors.name}
                        />
                        <Input
                            label={t('settings.email')}
                            type="email"
                            value={profile.email}
                            onChange={(e) =>
                                setProfile({
                                    ...profile,
                                    email: e.target.value,
                                })
                            }
                            error={errors.email}
                        />
                        <div className="flex justify-end">
                            <Button
                                onClick={saveProfile}
                                loading={savingProfile}
                            >
                                {t('common.save')}
                            </Button>
                        </div>
                    </div>
                </Card>

                <Card
                    title={t('settings.update_password')}
                    subtitle={t('settings.update_password_hint')}
                >
                    <div className="space-y-4">
                        <Input
                            label={t('settings.current_password')}
                            type="password"
                            value={password.current_password}
                            onChange={(e) =>
                                setPassword({
                                    ...password,
                                    current_password: e.target.value,
                                })
                            }
                            error={errors.current_password}
                        />
                        <Input
                            label={t('settings.new_password')}
                            type="password"
                            value={password.password}
                            onChange={(e) =>
                                setPassword({
                                    ...password,
                                    password: e.target.value,
                                })
                            }
                            error={errors.password}
                        />
                        <Input
                            label={t('settings.confirm_new_password')}
                            type="password"
                            value={password.password_confirmation}
                            onChange={(e) =>
                                setPassword({
                                    ...password,
                                    password_confirmation: e.target.value,
                                })
                            }
                            error={errors.password_confirmation}
                        />
                        <div className="flex justify-end">
                            <Button
                                onClick={savePassword}
                                loading={savingPassword}
                            >
                                {t('common.save')}
                            </Button>
                        </div>
                    </div>
                </Card>

                <Card
                    title={t('settings.danger_zone')}
                    className="border-red-200 dark:border-red-500/30"
                >
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <p className="text-ink text-sm font-medium dark:text-white">
                                {t('settings.delete_account')}
                            </p>
                            <p className="text-ink-faint text-xs">
                                {t('settings.delete_account_hint')}
                            </p>
                        </div>
                        <Button
                            variant="danger"
                            onClick={() => setDeleteOpen(true)}
                        >
                            {t('settings.delete')}
                        </Button>
                    </div>
                </Card>
            </div>

            <ConfirmDialog
                open={deleteOpen}
                onClose={() => setDeleteOpen(false)}
                onConfirm={() => router.delete('/user')}
                title={t('settings.delete_account')}
                message={t('settings.delete_confirm_message')}
                confirmLabel={t('settings.delete')}
            />
        </SettingsLayout>
    );
}
