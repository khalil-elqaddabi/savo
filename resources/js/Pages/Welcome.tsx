import { Brand } from '@/components/Brand';
import {
    IconBell,
    IconChart,
    IconGlobe,
    IconLock,
    IconMoon,
    IconPiggy,
    IconReceipt,
    IconRepeat,
    IconShield,
    IconSparkle,
    IconSun,
    IconTarget,
    IconWallet,
} from '@/components/Icons';
import { usePrefersTheme } from '@/lib/theme';
import { useTrans } from '@/lib/translation';
import type { SharedProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';

function SectionHeading({
    eyebrow,
    title,
    desc,
}: {
    eyebrow?: string;
    title: string;
    desc?: string;
}) {
    return (
        <div className="mx-auto mb-12 max-w-2xl text-center">
            {eyebrow ? (
                <span className="micro mb-3 block">{eyebrow}</span>
            ) : null}
            <h2 className="text-3xl font-bold tracking-tight sm:text-4xl">
                {title}
            </h2>
            {desc ? <p className="text-ink-soft mt-4 text-lg">{desc}</p> : null}
        </div>
    );
}

function Feature({
    icon,
    title,
    desc,
}: {
    icon: React.ReactNode;
    title: string;
    desc: string;
}) {
    return (
        <div className="card group p-6">
            <div className="bg-accent-soft text-accent mb-4 flex h-11 w-11 items-center justify-center rounded-xl">
                {icon}
            </div>
            <h3 className="text-ink text-lg font-semibold">{title}</h3>
            <p className="text-ink-soft mt-1.5 text-sm leading-relaxed">
                {desc}
            </p>
        </div>
    );
}

export default function Welcome() {
    const t = useTrans();
    const { app } = usePage<SharedProps>().props;
    const theme = usePrefersTheme();

    const switchLocale = (locale: string) =>
        router.post(
            '/preferences/language',
            { locale },
            { preserveScroll: true },
        );

    return (
        <div className="bg-canvas text-ink min-h-dvh overflow-x-hidden">
            <Head>
                <title>{t('welcome.meta_title')}</title>
                <meta
                    name="description"
                    content={t('welcome.meta_description')}
                />
                <meta property="og:title" content={t('welcome.meta_title')} />
                <meta
                    property="og:description"
                    content={t('welcome.meta_description')}
                />
                <meta property="og:type" content="website" />
            </Head>

            {/* ===================== Header ===================== */}
            <header className="border-line bg-canvas/85 sticky top-0 z-20 border-b backdrop-blur">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
                    <Brand href="/" withTagline={false} />

                    <div className="flex items-center gap-2">
                        <div className="border-line bg-surface-elevated shadow-card flex items-center rounded-full border p-0.5">
                            {app.supportedLocales.map((l) => (
                                <button
                                    key={l.code}
                                    type="button"
                                    onClick={() => switchLocale(l.code)}
                                    aria-pressed={app.locale === l.code}
                                    className={`rounded-full px-2.5 py-1 text-xs font-semibold uppercase transition ${
                                        app.locale === l.code
                                            ? 'bg-accent text-accent-contrast'
                                            : 'text-ink-soft hover:text-ink'
                                    }`}
                                >
                                    {l.code}
                                </button>
                            ))}
                        </div>

                        <button
                            type="button"
                            onClick={theme.toggle}
                            className="border-line bg-surface-elevated text-ink-soft shadow-card hover:text-ink rounded-full border p-2.5 transition"
                            aria-label={t('common.theme')}
                        >
                            {theme.theme === 'dark' ? (
                                <IconSun
                                    size={18}
                                    className="text-amberbrand"
                                />
                            ) : (
                                <IconMoon size={18} />
                            )}
                        </button>

                        <Link
                            href="/login"
                            className="btn-ghost hidden px-3 py-2 text-sm font-medium sm:inline-flex"
                        >
                            {t('auth.login')}
                        </Link>
                        <Link href="/register" className="btn-primary">
                            {t('auth.register')}
                        </Link>
                    </div>
                </div>
            </header>

            <main>
                {/* ===================== Hero ===================== */}
                <section className="relative overflow-hidden">
                    <div className="bg-accent/12 dark:bg-accent/10 pointer-events-none absolute -top-24 -right-24 h-72 w-72 rounded-full blur-3xl" />
                    <div className="bg-accent/8 dark:bg-accent/5 pointer-events-none absolute top-40 -left-24 h-72 w-72 rounded-full blur-3xl" />

                    <div className="relative mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:items-center lg:py-24">
                        <div>
                            <span className="badge bg-accent-soft text-accent mb-4">
                                <IconShield size={13} />
                                {t('welcome.local_first')}
                            </span>
                            <h1 className="text-4xl leading-tight font-extrabold tracking-tight sm:text-5xl">
                                {t('welcome.hero_title')}
                            </h1>
                            <p className="text-ink-soft mt-4 max-w-xl text-lg leading-relaxed">
                                {t('welcome.hero_subtitle')}
                            </p>
                            <div className="mt-8 flex flex-wrap gap-3">
                                <Link
                                    href="/register"
                                    className="btn-primary text-base"
                                >
                                    {t('welcome.get_started')}
                                </Link>
                                <Link
                                    href="/login"
                                    className="btn-secondary text-base"
                                >
                                    {t('auth.login')}
                                </Link>
                            </div>
                        </div>

                        {/* At-a-glance preview — illustrative, no fabricated figures */}
                        <div className="relative mx-auto w-full max-w-md">
                            <div className="card shadow-lift relative space-y-4 p-6">
                                <div className="flex items-center justify-between">
                                    <p className="text-ink-soft text-sm font-medium">
                                        {t('dashboard.safe_to_spend')}
                                    </p>
                                    <span className="bg-accent-soft text-accent rounded-full px-2.5 py-0.5 text-xs font-semibold">
                                        {t('welcome.preview_live')}
                                    </span>
                                </div>

                                <div className="grid grid-cols-2 gap-3 text-sm">
                                    <div className="card-soft p-3">
                                        <p className="text-ink-soft flex items-center gap-2">
                                            <IconWallet
                                                size={16}
                                                className="text-accent"
                                            />
                                            {t('welcome.preview_accounts')}
                                        </p>
                                    </div>
                                    <div className="card-soft p-3">
                                        <p className="text-ink-soft flex items-center gap-2">
                                            <IconChart
                                                size={16}
                                                className="text-accent"
                                            />
                                            {t('welcome.preview_reports')}
                                        </p>
                                    </div>
                                    <div className="card-soft p-3">
                                        <p className="text-ink-soft flex items-center gap-2">
                                            <IconTarget
                                                size={16}
                                                className="text-accent"
                                            />
                                            {t('welcome.preview_goals')}
                                        </p>
                                    </div>
                                    <div className="card-soft p-3">
                                        <p className="text-ink-soft flex items-center gap-2">
                                            <IconRepeat
                                                size={16}
                                                className="text-accent"
                                            />
                                            {t('welcome.preview_recurring')}
                                        </p>
                                    </div>
                                </div>

                                <div className="space-y-3">
                                    <p className="text-ink-soft text-sm">
                                        {t('welcome.preview_budget_title')}
                                    </p>
                                    <div className="bg-line h-2.5 rounded-full dark:bg-white/10">
                                        <div className="bg-accent h-full w-4/5 rounded-full" />
                                    </div>
                                    <div className="flex flex-wrap gap-2 pt-1">
                                        <span className="bg-accent/15 text-accent rounded-full px-3 py-1 text-xs font-semibold">
                                            {t('welcome.preview_budgets')}
                                        </span>
                                        <span className="bg-accent/15 text-accent rounded-full px-3 py-1 text-xs font-semibold">
                                            {t('welcome.preview_bills')}
                                        </span>
                                        <span className="bg-accent/15 text-accent rounded-full px-3 py-1 text-xs font-semibold">
                                            {t('welcome.preview_debts')}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* ===================== Value proposition ===================== */}
                <section className="border-line bg-surface-soft/60 border-t">
                    <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                        <SectionHeading
                            eyebrow={t('welcome.value_eyebrow')}
                            title={t('welcome.value_title')}
                            desc={t('welcome.value_desc')}
                        />
                        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            <Feature
                                icon={<IconWallet size={20} />}
                                title={t('welcome.f_wallet')}
                                desc={t('welcome.f_wallet_desc')}
                            />
                            <Feature
                                icon={<IconTarget size={20} />}
                                title={t('welcome.f_goals')}
                                desc={t('welcome.f_goals_desc')}
                            />
                            <Feature
                                icon={<IconRepeat size={20} />}
                                title={t('welcome.f_recurring')}
                                desc={t('welcome.f_recurring_desc')}
                            />
                            <Feature
                                icon={<IconChart size={20} />}
                                title={t('welcome.f_analytics')}
                                desc={t('welcome.f_analytics_desc')}
                            />
                            <Feature
                                icon={<IconPiggy size={20} />}
                                title={t('welcome.f_budget')}
                                desc={t('welcome.f_budget_desc')}
                            />
                            <Feature
                                icon={<IconBell size={20} />}
                                title={t('welcome.f_alerts')}
                                desc={t('welcome.f_alerts_desc')}
                            />
                        </div>
                    </div>
                </section>

                {/* ===================== Assistant / AI ===================== */}
                <section className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                    <div className="card card-dark grid items-center gap-8 overflow-hidden p-8 sm:p-10 lg:grid-cols-2">
                        <div>
                            <span className="badge bg-accent-soft text-accent mb-4">
                                <IconSparkle size={13} />
                                {t('welcome.ai_badge')}
                            </span>
                            <h2 className="text-3xl font-bold tracking-tight">
                                {t('welcome.ai_title')}
                            </h2>
                            <p className="text-ink-soft mt-4 text-lg leading-relaxed">
                                {t('welcome.ai_desc')}
                            </p>
                            <ul className="text-ink-soft mt-6 space-y-3">
                                {[
                                    t('welcome.ai_point_1'),
                                    t('welcome.ai_point_2'),
                                    t('welcome.ai_point_3'),
                                ].map((point) => (
                                    <li
                                        key={point}
                                        className="flex items-start gap-3"
                                    >
                                        <IconShield
                                            size={18}
                                            className="text-accent mt-0.5 shrink-0"
                                        />
                                        <span>{point}</span>
                                    </li>
                                ))}
                            </ul>
                            <div className="mt-8">
                                <Link href="/register" className="btn-primary">
                                    {t('welcome.get_started')}
                                </Link>
                            </div>
                        </div>
                        <div className="card-soft h-full space-y-3 overflow-hidden p-6">
                            <div className="flex items-center gap-3">
                                <div className="bg-accent text-accent-contrast flex h-9 w-9 items-center justify-center rounded-full">
                                    <IconSparkle size={18} />
                                </div>
                                <div>
                                    <p className="text-ink text-sm font-semibold">
                                        {t('welcome.ai_chat_title')}
                                    </p>
                                    <p className="text-ink-faint text-xs">
                                        {t('welcome.ai_chat_sub')}
                                    </p>
                                </div>
                            </div>
                            <div className="bg-user-bubble border-user-bubble-border text-ink rounded-2xl border p-3 text-sm">
                                {t('welcome.ai_chat_q')}
                            </div>
                            <div className="bg-surface-elevated border-line rounded-2xl border p-3 text-sm">
                                {t('welcome.ai_chat_a')}
                            </div>
                        </div>
                    </div>
                </section>

                {/* ===================== Security ===================== */}
                <section className="border-line bg-surface-soft/60 border-t">
                    <div className="mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2">
                        <div className="relative">
                            <div className="bg-accent/10 pointer-events-none absolute -inset-6 rounded-3xl blur-2xl" />
                            <div className="card relative grid grid-cols-2 gap-4 p-6">
                                <div className="card-soft flex flex-col items-center gap-2 p-4 text-center">
                                    <IconShield
                                        size={24}
                                        className="text-accent"
                                    />
                                    <p className="text-ink text-sm font-medium">
                                        {t('welcome.sec_2fa')}
                                    </p>
                                </div>
                                <div className="card-soft flex flex-col items-center gap-2 p-4 text-center">
                                    <IconLock
                                        size={24}
                                        className="text-accent"
                                    />
                                    <p className="text-ink text-sm font-medium">
                                        {t('welcome.sec_lock')}
                                    </p>
                                </div>
                                <div className="card-soft flex flex-col items-center gap-2 p-4 text-center">
                                    <IconGlobe
                                        size={24}
                                        className="text-accent"
                                    />
                                    <p className="text-ink text-sm font-medium">
                                        {t('welcome.sec_local')}
                                    </p>
                                </div>
                                <div className="card-soft flex flex-col items-center gap-2 p-4 text-center">
                                    <IconReceipt
                                        size={24}
                                        className="text-accent"
                                    />
                                    <p className="text-ink text-sm font-medium">
                                        {t('welcome.sec_export')}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div>
                            <span className="micro mb-3 block">
                                {t('welcome.sec_eyebrow')}
                            </span>
                            <h2 className="text-3xl font-bold tracking-tight sm:text-4xl">
                                {t('welcome.sec_title')}
                            </h2>
                            <p className="text-ink-soft mt-4 text-lg leading-relaxed">
                                {t('welcome.sec_desc')}
                            </p>
                            <div className="mt-8">
                                <Link
                                    href="/register"
                                    className="btn-secondary text-base"
                                >
                                    {t('welcome.get_started')}
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>

                {/* ===================== Final CTA ===================== */}
                <section className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                    <div className="card border-accent/30 bg-accent-soft/40 relative overflow-hidden p-10 text-center sm:p-14">
                        <h2 className="mx-auto max-w-2xl text-3xl font-bold tracking-tight sm:text-4xl">
                            {t('welcome.cta_title')}
                        </h2>
                        <p className="text-ink-soft mx-auto mt-4 max-w-xl text-lg">
                            {t('welcome.cta_desc')}
                        </p>
                        <div className="mt-8 flex flex-wrap justify-center gap-3">
                            <Link
                                href="/register"
                                className="btn-primary text-base"
                            >
                                {t('welcome.get_started')}
                            </Link>
                            <Link
                                href="/login"
                                className="btn-secondary text-base"
                            >
                                {t('auth.login')}
                            </Link>
                        </div>
                    </div>
                </section>
            </main>

            {/* ===================== Footer ===================== */}
            <footer className="border-line border-t">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-8 sm:px-6">
                    <div className="flex items-center gap-2.5">
                        <Brand href="/" withTagline={false} />
                    </div>
                    <p className="text-ink-faint text-sm">
                        © {new Date().getFullYear()} {app.name}
                    </p>
                </div>
            </footer>
        </div>
    );
}
