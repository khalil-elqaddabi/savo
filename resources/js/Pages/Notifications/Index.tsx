import AppLayout from '@/Layouts/AppLayout';
import {
    IconAlert,
    IconCalendar,
    IconCheck,
    IconTarget,
    IconWallet,
} from '@/components/Icons';
import { Badge, Button, Card, EmptyState, PageLoading } from '@/components/ui';
import { PageHeader } from '@/components/ui/PageHeader';
import { useTrans } from '@/lib/translation';
import type { SharedProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useState, type ComponentType } from 'react';

interface NotificationItem {
    id: string;
    kind: string;
    title: string;
    message: string;
    related_type?: string | null;
    related_id?: number | null;
    data?: Record<string, unknown> | null;
    read_at?: string | null;
    created_at: string;
}

interface Props extends SharedProps {
    notifications: {
        data: NotificationItem[];
        total: number;
        current_page: number;
        last_page: number;
        next_page_url: string | null;
        prev_page_url: string | null;
    };
    unreadCount: number;
}

const kindIcon: Record<string, ComponentType<{ size?: number }>> = {
    budget_alert: IconAlert,
    upcoming_bill: IconCalendar,
    goal_progress: IconTarget,
    unusual_spending: IconWallet,
};

const kindTone: Record<string, string> = {
    budget_alert: 'bg-coral/10 text-coral',
    upcoming_bill: 'bg-accent-soft text-accent',
    goal_progress: 'bg-mint/10 text-mint',
    unusual_spending: 'bg-amberbrand/10 text-amberbrand',
};

function relativeTime(iso: string | undefined): string {
    if (!iso) return '';
    const diff = Date.now() - new Date(iso).getTime();
    const minutes = Math.floor(diff / 60000);
    if (minutes < 1) return 'now';
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d`;
    return new Date(iso).toLocaleDateString();
}

export default function NotificationsIndex() {
    const t = useTrans();
    const { notifications, unreadCount } = usePage<Props>().props;
    const [loadingId, setLoadingId] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);

    const paginator = notifications as unknown as Props['notifications'];
    const items = paginator?.data ?? [];

    const markRead = (n: NotificationItem) => {
        if (n.read_at) return;
        setLoadingId(n.id);
        router.post(
            `/notifications/${n.id}/read`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setLoadingId(null),
            },
        );
    };

    const markAll = () => {
        setSubmitting(true);
        router.post(
            '/notifications/mark-all-read',
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const goToPage = (url: string | null) => {
        if (!url) return;
        router.get(url, {}, { preserveScroll: false, preserveState: true });
    };

    if (!paginator) return <PageLoading />;

    return (
        <AppLayout>
            <PageHeader
                title={t('smart_notification.notifications')}
                subtitle={`${unreadCount} ${t('smart_notification.unread')}`}
                action={
                    items.some((n) => !n.read_at) ? (
                        <Button
                            variant="soft"
                            size="sm"
                            onClick={markAll}
                            loading={submitting}
                        >
                            <IconCheck size={15} />
                            {t('smart_notification.mark_all_read')}
                        </Button>
                    ) : undefined
                }
            />

            {items.length === 0 ? (
                <EmptyState
                    icon={<IconCheck size={28} />}
                    title={t('smart_notification.empty')}
                />
            ) : (
                <Card padding={false}>
                    <div className="divide-line divide-y dark:divide-white/5">
                        {items.map((n) => {
                            const Icon = kindIcon[n.kind] ?? IconAlert;
                            const tone =
                                kindTone[n.kind] ??
                                'bg-accent-soft text-accent';
                            const unread = !n.read_at;
                            return (
                                <div
                                    key={n.id}
                                    className={`flex items-start gap-3 px-4 py-3.5 ${
                                        unread ? 'bg-accent/[0.03]' : ''
                                    }`}
                                >
                                    <span
                                        className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${tone}`}
                                    >
                                        <Icon size={18} />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center justify-between gap-2">
                                            <p className="text-ink truncate text-sm font-semibold dark:text-white">
                                                {n.title}
                                            </p>
                                            <span className="text-ink-faint shrink-0 text-xs tabular-nums">
                                                {relativeTime(n.created_at)}
                                            </span>
                                        </div>
                                        <p className="text-ink-soft mt-0.5 text-sm">
                                            {n.message}
                                        </p>
                                        {unread ? (
                                            <Badge tone="info" className="mt-2">
                                                {t('smart_notification.unread')}
                                            </Badge>
                                        ) : null}
                                    </div>
                                    {unread ? (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => markRead(n)}
                                            loading={loadingId === n.id}
                                        >
                                            {t('smart_notification.mark_read')}
                                        </Button>
                                    ) : null}
                                </div>
                            );
                        })}
                    </div>

                    {paginator.last_page > 1 ? (
                        <div className="border-line flex items-center justify-between gap-3 border-t px-5 py-3">
                            <Button
                                variant="secondary"
                                size="sm"
                                disabled={!paginator.prev_page_url}
                                onClick={() =>
                                    goToPage(paginator.prev_page_url)
                                }
                            >
                                {t('common.prev')}
                            </Button>
                            <span className="text-ink-faint text-xs tabular-nums">
                                {paginator.current_page} / {paginator.last_page}
                            </span>
                            <Button
                                variant="secondary"
                                size="sm"
                                disabled={!paginator.next_page_url}
                                onClick={() =>
                                    goToPage(paginator.next_page_url)
                                }
                            >
                                {t('common.next')}
                            </Button>
                        </div>
                    ) : null}
                </Card>
            )}
        </AppLayout>
    );
}
