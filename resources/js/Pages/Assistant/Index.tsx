import AppLayout from '@/Layouts/AppLayout';
import { IconPlus, IconSend, IconSparkle } from '@/components/Icons';
import { Button, Card, EmptyState } from '@/components/ui';
import { PageHeader } from '@/components/ui/PageHeader';
import { useTrans } from '@/lib/translation';
import type { SharedProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';

interface Conversation {
    id: number;
    title: string;
    message_count: number;
    updated_at?: string;
}

interface Props extends SharedProps {
    conversations: Conversation[];
}

export default function AssistantIndex() {
    const t = useTrans();
    const { conversations } = usePage<Props>().props;

    return (
        <AppLayout>
            <PageHeader
                title={t('assistant.title')}
                subtitle={t('assistant.subtitle')}
                action={
                    <Button
                        onClick={() =>
                            router.post('/assistant/create', {
                                title: t('assistant.new_chat'),
                            })
                        }
                    >
                        <IconPlus size={16} />
                        {t('assistant.new_chat')}
                    </Button>
                }
            />

            <Card className="border-accent/25 bg-accent-soft/40 mb-5">
                <div className="flex items-start gap-3">
                    <span className="bg-accent text-accent-contrast flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl">
                        <IconSparkle size={20} />
                    </span>
                    <div>
                        <h3 className="text-ink font-semibold dark:text-white">
                            {t('assistant.hero_title')}
                        </h3>
                        <p className="text-ink-soft mt-0.5 text-sm dark:text-white/70">
                            {t('assistant.hero_hint')}
                        </p>
                    </div>
                </div>
            </Card>

            {conversations.length === 0 ? (
                <EmptyState
                    icon={<IconSparkle size={28} />}
                    title={t('assistant.empty_title')}
                    description={t('assistant.empty_hint')}
                    action={
                        <Button
                            onClick={() =>
                                router.post('/assistant/create', {
                                    title: t('assistant.new_chat'),
                                })
                            }
                        >
                            <IconSend size={16} />
                            {t('assistant.start')}
                        </Button>
                    }
                />
            ) : (
                <div className="space-y-2">
                    {conversations.map((c) => (
                        <Link
                            key={c.id}
                            href={`/assistant/${c.id}`}
                            className="group border-line bg-surface hover:border-accent/40 flex items-center gap-3 rounded-2xl border px-4 py-3 transition hover:shadow-sm dark:border-white/10 dark:bg-white/[0.03]"
                        >
                            <span className="bg-surface-strong text-ink-faint flex h-9 w-9 shrink-0 items-center justify-center rounded-xl dark:bg-white/5">
                                <IconSparkle size={16} />
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="text-ink truncate text-sm font-medium dark:text-white">
                                    {c.title}
                                </p>
                                <p className="text-ink-faint text-xs">
                                    {c.message_count} {t('assistant.messages')}{' '}
                                    · {c.updated_at ?? ''}
                                </p>
                            </div>
                        </Link>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
