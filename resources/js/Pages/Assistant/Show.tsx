import AppLayout from '@/Layouts/AppLayout';
import {
    IconAlert,
    IconArrowLeft,
    IconChart,
    IconSend,
    IconSparkle,
    IconTarget,
    IconTrash,
    IconTrendingUp,
    IconWallet,
} from '@/components/Icons';
import { Badge } from '@/components/ui';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { IconSpinner } from '@/components/ui/icons';
import { useTrans } from '@/lib/translation';
import type { SharedProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import {
    lazy,
    Suspense,
    useEffect,
    useRef,
    useState,
    type ReactNode,
} from 'react';

/* ------------------------------------------------------------------ *
 * Types
 * ------------------------------------------------------------------ */

const AssistantMarkdown = lazy(() =>
    import('./Markdown').then((m) => ({ default: m.Markdown })),
);

interface Message {
    id: number;
    role: string;
    content: string;
    created_at?: string;
}

interface Props extends SharedProps {
    conversation: { id: number; title: string };
    messages: Message[];
    aiEnabled: boolean;
    errors?: Record<string, string>;
}

/* ------------------------------------------------------------------ *
 * Shared pieces
 * ------------------------------------------------------------------ */

function AssistantAvatar({ size = 'sm' }: { size?: 'sm' | 'md' | 'lg' }) {
    const box =
        size === 'lg'
            ? 'h-16 w-16 rounded-[22px]'
            : size === 'md'
              ? 'h-9 w-9 rounded-xl'
              : 'h-7 w-7 rounded-[9px]';
    const icon = size === 'lg' ? 30 : size === 'md' ? 18 : 15;
    return (
        <span
            className={`from-accent to-accent-strong text-accent-contrast relative inline-flex shrink-0 items-center justify-center bg-gradient-to-br shadow-[0_2px_8px_-2px_rgba(13,143,116,0.5)] ring-1 ring-white/10 ${box}`}
        >
            <IconSparkle size={icon} />
        </span>
    );
}

/* Empty conversation: premium centered welcome on a soft card */
function EmptyChat({ onSuggest }: { onSuggest: (message: string) => void }) {
    const t = useTrans();
    const cards = [
        {
            key: 'balance',
            title: t('assistant.sug_balance'),
            desc: t('assistant.sug_balance_desc'),
            icon: IconWallet,
        },
        {
            key: 'spending',
            title: t('assistant.sug_spending'),
            desc: t('assistant.sug_spending_desc'),
            icon: IconTrendingUp,
        },
        {
            key: 'budget',
            title: t('assistant.sug_budget'),
            desc: t('assistant.sug_budget_desc'),
            icon: IconTarget,
        },
        {
            key: 'summary',
            title: t('assistant.sug_summary'),
            desc: t('assistant.sug_summary_desc'),
            icon: IconChart,
        },
    ] as const;

    return (
        <div className="mx-auto flex h-full w-full max-w-3xl flex-col items-center justify-center gap-8 text-center">
            <div className="animate-ingress flex flex-col items-center gap-3">
                <div className="bg-accent-soft text-accent rounded-2xl p-3">
                    <IconSparkle size={18} />
                </div>
                <div>
                    <h2 className="text-ink text-[17px] font-semibold tracking-[-0.01em] dark:text-white">
                        Savo AI
                    </h2>
                    <p className="text-ink-faint mt-1 text-sm">
                        {t('assistant.empty_welcome')}
                    </p>
                </div>
                <p className="text-ink-soft mt-1 max-w-sm text-[13.5px] leading-relaxed dark:text-[#9aa1ab]">
                    {t('assistant.empty_hint')}
                </p>
            </div>

            <div className="grid w-full max-w-xl grid-cols-1 gap-2.5 sm:grid-cols-2">
                {cards.map((c) => {
                    const Icon = c.icon;
                    return (
                        <button
                            key={c.key}
                            type="button"
                            onClick={() => onSuggest(c.title)}
                            className="group animate-fade-up border-line hover:border-accent/40 hover:bg-surface-soft/70 bg-surface-elevated shadow-card flex items-center gap-3 rounded-xl border p-3.5 text-start transition-colors dark:hover:bg-white/[0.05]"
                            style={{
                                animationDelay: `${cards.indexOf(c) * 60}ms`,
                            }}
                        >
                            <span className="bg-surface-soft text-ink-soft group-hover:text-accent flex h-9 w-9 shrink-0 items-center justify-center rounded-lg transition-colors dark:bg-white/5">
                                <Icon size={17} />
                            </span>
                            <span className="min-w-0">
                                <span className="text-ink block text-[13.5px] font-medium dark:text-white">
                                    {c.title}
                                </span>
                                <span className="text-ink-faint mt-0.5 block text-xs">
                                    {c.desc}
                                </span>
                            </span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

function TypingIndicator() {
    const t = useTrans();
    return (
        <div
            className="animate-chat-in flex items-center gap-2.5"
            role="status"
        >
            <AssistantAvatar size="md" />
            <div className="border-line dark:border-line bg-surface-elevated shadow-card flex items-center gap-1.5 rounded-2xl border px-4 py-2.5 dark:bg-white/[0.04]">
                <span className="typing-dot" />
                <span
                    className="typing-dot"
                    style={{ animationDelay: '150ms' }}
                />
                <span
                    className="typing-dot"
                    style={{ animationDelay: '300ms' }}
                />
                <span className="text-ink-faint ms-2 text-xs font-medium">
                    {t('assistant.thinking')}
                </span>
            </div>
        </div>
    );
}

/* User message: subtle accent-tinted surface, aligned to logical end */
function UserBubble({ children }: { children: ReactNode }) {
    return (
        <div className="animate-chat-in mb-4 flex justify-end">
            <div className="user-bubble flex max-w-[82%] items-start gap-2.5 px-3.5 py-2.5 sm:max-w-[72%]">
                <span
                    aria-hidden="true"
                    className="bg-accent mt-[7px] h-1.5 w-1.5 shrink-0 rounded-full"
                />
                <p className="text-[14.5px] leading-[1.55] break-words whitespace-pre-wrap">
                    {children}
                </p>
            </div>
        </div>
    );
}

/* Assistant message: avatar on the start, content in a soft filled bubble */
function AssistantResponse({ children }: { children: ReactNode }) {
    return (
        <div className="animate-chat-in mb-4 flex items-start gap-3">
            <AssistantAvatar size="md" />
            <div className="min-w-0 flex-1">
                <div className="border-line dark:border-line bg-surface-elevated shadow-card max-w-[92%] rounded-2xl rounded-es-md border px-4 py-3.5 sm:max-w-[82%] dark:bg-white/[0.04]">
                    <div className="text-ink-faint mb-3 flex items-center gap-1.5 text-[11px] font-medium tracking-[0.06em] uppercase">
                        <span className="bg-mint h-1.5 w-1.5 rounded-full" />
                        Savo
                    </div>
                    <div className="text-pretty">{children}</div>
                </div>
            </div>
        </div>
    );
}

/* ------------------------------------------------------------------ *
 * Page
 * ------------------------------------------------------------------ */

export default function AssistantShow() {
    const t = useTrans();
    const {
        conversation,
        messages,
        aiEnabled,
        errors = {},
    } = usePage<Props>().props;
    const [input, setInput] = useState('');
    const [sending, setSending] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const bottomRef = useRef<HTMLDivElement>(null);
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const sendingRef = useRef(false);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }, [messages, sending]);

    useEffect(() => {
        const el = textareaRef.current;
        if (!el) return;
        el.style.height = '0px';
        el.style.height = `${Math.min(el.scrollHeight, 132)}px`;
    }, [input]);

    const send = (message?: string) => {
        const text = (message ?? input).trim();
        if (sendingRef.current || !text) return;
        sendingRef.current = true;
        setSending(true);
        router.post(
            `/assistant/${conversation.id}/send`,
            { message: text },
            {
                onFinish: () => {
                    sendingRef.current = false;
                    setSending(false);
                },
            },
        );
        setInput('');
    };

    return (
        <AppLayout>
            <div className="flex h-[calc(100dvh-8.5rem)] flex-col lg:h-[calc(100dvh-4.5rem)]">
                {/* Workspace header */}
                <header className="border-line/70 dark:border-line/70 shrink-0 border-b">
                    <div className="mx-auto flex h-16 w-full max-w-[1100px] items-center justify-between gap-3 px-4 sm:px-8">
                        <div className="flex min-w-0 items-center gap-3">
                            <AssistantAvatar />
                            <div className="min-w-0">
                                <div className="flex items-center gap-2">
                                    <h1 className="text-ink truncate text-[15px] font-semibold tracking-[-0.01em] dark:text-white">
                                        {t('assistant.ai_name')}
                                    </h1>
                                    <span
                                        className="bg-mint h-1.5 w-1.5 shrink-0 rounded-full"
                                        aria-hidden="true"
                                    />
                                </div>
                                <p className="text-ink-faint truncate text-xs">
                                    {t('assistant.ai_subtitle')}
                                </p>
                            </div>
                        </div>
                        <div className="flex shrink-0 items-center gap-1.5">
                            <Link
                                href="/assistant"
                                aria-label={t('common.back')}
                                className="btn text-ink-soft hover:bg-surface-strong hover:text-ink"
                            >
                                <IconArrowLeft size={16} />
                                <span className="hidden sm:inline">
                                    {t('common.back')}
                                </span>
                            </Link>
                            <button
                                type="button"
                                onClick={() => setDeleteOpen(true)}
                                aria-label={t('common.delete')}
                                title={t('common.delete')}
                                className="btn text-coral hover:bg-coral/10 hover:text-coral"
                            >
                                <IconTrash size={16} />
                            </button>
                        </div>
                    </div>
                </header>

                {/* Conversation feed */}
                <section className="chat-surface min-h-0 flex-1 overflow-y-auto">
                    <div className="mx-auto flex min-h-full w-full max-w-[1100px] flex-col px-4 pt-4 pb-10 sm:px-8">
                        {!aiEnabled ? (
                            <Badge tone="warning" className="mb-4 self-start">
                                {t('assistant.using_fallback')}
                            </Badge>
                        ) : null}

                        <div
                            role="log"
                            aria-live="polite"
                            aria-relevant="additions"
                        >
                            {messages.length === 0 ? (
                                <div className="h-full">
                                    <EmptyChat onSuggest={send} />
                                </div>
                            ) : (
                                <Suspense
                                    fallback={
                                        <AssistantResponse>
                                            <div className="animate-pulse space-y-2.5 py-1">
                                                <div className="skeleton h-3.5 w-3/4 rounded-md" />
                                                <div className="skeleton h-3.5 w-1/2 rounded-md" />
                                                <div className="skeleton h-3.5 w-5/6 rounded-md" />
                                            </div>
                                        </AssistantResponse>
                                    }
                                >
                                    {messages.map((m) =>
                                        m.role === 'user' ? (
                                            <UserBubble key={m.id}>
                                                {m.content}
                                            </UserBubble>
                                        ) : (
                                            <AssistantResponse key={m.id}>
                                                <AssistantMarkdown
                                                    content={m.content}
                                                />
                                            </AssistantResponse>
                                        ),
                                    )}
                                </Suspense>
                            )}

                            {sending ? <TypingIndicator /> : null}
                            <div ref={bottomRef} />
                        </div>
                    </div>
                </section>

                {/* Floating composer */}
                <footer className="border-line/70 dark:border-line/70 bg-surface/80 shrink-0 border-t backdrop-blur-md">
                    <div className="mx-auto w-full max-w-[1100px] px-4 pt-3 pb-4 sm:px-8 sm:pt-4">
                        {errors.message ? (
                            <div
                                role="alert"
                                className="text-coral border-coral/25 bg-coral/5 mb-3 flex items-start gap-2 rounded-xl border px-3 py-2 text-xs font-medium"
                            >
                                <IconAlert
                                    size={14}
                                    className="mt-0.5 shrink-0"
                                />
                                <span>{errors.message}</span>
                            </div>
                        ) : null}

                        <div className="border-line-strong dark:border-line-strong shadow-lift focus-within:border-accent/60 focus-within:ring-accent/20 bg-surface-elevated flex items-end gap-2 rounded-[20px] border p-1.5 transition-all duration-150 focus-within:ring-[3px] dark:bg-[#21262e] dark:shadow-[0_8px_30px_-8px_rgba(0,0,0,0.9)]">
                            <textarea
                                ref={textareaRef}
                                rows={1}
                                value={input}
                                onChange={(e) => setInput(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' && !e.shiftKey) {
                                        e.preventDefault();
                                        send();
                                    }
                                }}
                                disabled={sending}
                                placeholder={t('assistant.placeholder')}
                                aria-label={t('assistant.placeholder')}
                                className="text-ink placeholder:text-ink-faint caret-accent max-h-32 min-h-[38px] flex-1 resize-none bg-transparent px-3 py-2 text-[14.5px] leading-[1.6] outline-none disabled:cursor-not-allowed dark:text-[#e9ebee] dark:placeholder:text-[#8b93a1]"
                            />
                            <button
                                type="button"
                                onClick={() => send()}
                                disabled={sending || !input.trim()}
                                aria-label={t('assistant.send')}
                                className="flex h-9 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[var(--color-btn-grad-from)] to-[var(--color-btn-grad-to)] text-white transition-all duration-150 hover:opacity-90 active:scale-95 disabled:cursor-not-allowed disabled:opacity-35"
                            >
                                {sending ? (
                                    <IconSpinner
                                        size={17}
                                        className="animate-spin"
                                    />
                                ) : (
                                    <IconSend size={17} />
                                )}
                            </button>
                        </div>
                    </div>
                </footer>
            </div>

            <ConfirmDialog
                open={deleteOpen}
                onClose={() => setDeleteOpen(false)}
                onConfirm={() => {
                    router.delete(`/assistant/${conversation.id}`);
                    setDeleteOpen(false);
                }}
                title={t('assistant.delete_title')}
                message={t('assistant.delete_hint')}
                confirmLabel={t('common.delete')}
            />
        </AppLayout>
    );
}
