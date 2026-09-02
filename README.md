# Savo

A personal finance web application built with **Laravel 13 + Inertia + React (TypeScript) + Tailwind CSS v4 + Vite + MySQL**.

Savo lets you manage accounts, transactions (including transfers), categories, budgets, savings goals, recurring transactions, monthly forecasting, safe-to-spend, analytics, and a built-in AI assistant. It is fully localized in French, Arabic (with real RTL), and English, and supports light/dark themes.

## Features

- **Authentication** — register, login, logout, password reset, email verification, Google OAuth, TOTP 2FA with recovery codes (via Laravel Fortify).
- **Financial engine**
  - Accounts (cash, bank, savings, credit card, digital wallet) with starting balances.
  - Transactions (income / expense / transfer) — transfers never count as income or expense.
  - Categories — global/system defaults plus per-user categories.
  - Budgets — weekly/monthly, overall or per-category (expense) scopes.
  - Savings goals with contribution tracking and deadlines.
  - Recurring transactions (daily / weekly / monthly / yearly).
  - Forecasting, safe-to-spend, analytics, and deterministic budget/insight calculations.
- **Reports** — period selection, summary cards, 6-month trends, balance history, category/account breakdowns, budgets and goals progress, average daily spend.
- **AI assistant** — optional; works without an API key (degrades gracefully). It only ever receives financial context computed server-side from the user's own data.
- **Localization** — French (default), Arabic (RTL), English.
- **Themes** — light / dark mode.
- **Multi-user isolation** — every resource is scoped to its owning user via policies.

## Requirements

- PHP 8.2+ (developed against 8.5) with `pdo_mysql`
- Composer 2
- Node.js 20+ and npm
- MySQL 8+

> **Note on arithmetic:** `bcmath` is intentionally not a hard requirement. All authoritative money math goes through `App\Support\Money`, which uses integer cents, so balances stay deterministic.

## Installation

```bash
# 1. Install dependencies
composer install
npm install

# 2. Configure environment
cp .env.example .env
php artisan key:generate
#   -> edit .env: set your MySQL credentials, APP_LOCALE (fr|ar|en), APP_URL,
#      and optionally GOOGLE_CLIENT_ID/SECRET and AI_API_KEY.

# 3. Point App URL at your local host so OAuth + email links resolve.
#    (Example: APP_URL=http://localhost:8000)

# 4. Migrate and seed (creates system categories plus a demo user)
php artisan migrate
php artisan db:seed

# 5. Build the frontend
npm run build
#    — or, during development —
npm run dev
```

### Demo user (after seeding)

- **Email:** `demo@savo.test`
- **Password:** `password`

## Configuration

| Variable | Purpose |
| --- | --- |
| `APP_LOCALE` | Default locale (`fr`, `ar`, `en`) |
| `DB_*` | MySQL connection settings |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URI` | Google OAuth (blank disables the button) |
| `AI_PROVIDER` | `openai` or `openai-compatible` (see AI Assistant below) |
| `AI_API_KEY` | Assistant key (blank disables the assistant gracefully) |
| `AI_MODEL` | Model name used by the assistant |
| `AI_BASE_URL` | API base URL for the provider |

### AI Assistant

The assistant is a provider abstraction (`App\Services\AI\AIServiceInterface`) with
a configurable implementation (`OpenAIService`, or `NullAIProvider` when no key is
set). The provider is a transport layer only — it never touches the database.

**Supported providers:** only an OpenAI **chat-completions** transport is
implemented today — `AI_PROVIDER=openai`, or `AI_PROVIDER=openai-compatible`
(which reuses the same transport against `AI_BASE_URL` / `AI_MODEL`, e.g. any
OpenAI-compatible gateway). Anthropic is **not** implemented; do not set
`AI_PROVIDER=anthropic` (any unrecognized value safely degrades to the same
OpenAI-compatible transport rather than failing).

Financial data is served through a controlled, server-side **tool layer**
(`FinancialToolRegistry` + the tools in `App\Services\AI\Tools`): `getFinancialSummary`,
`getAccountBalances`, `getCategorySpending`, `getBudgetStatus`, `getSavingsGoalStatus`,
`getRecurringUpcoming`, `getForecast`, `getSafeToSpend`, `compareMonths`,
`getAffordability` and `getRecentTransactions`. Each tool is scoped to the
authenticated user, returns only aggregated/minimal data, and reuses the
deterministic finance engine services — the model can *request* a tool but the
application *executes* it, so it never computes authoritative numbers itself.

- Works fully without an API key (degrades to a friendly localized message).
- Answers in the user's selected language (FR / AR / EN).
- Never exposes passwords, tokens, 2FA/recovery codes, API keys, or raw ledger
  details; transfers are never reported as spending or income.

## Running the tests

The test suite is written with [Pest](https://pestphp.com). Set `DB_DATABASE` to a dedicated test database (the suite uses `savo_test` by default) then run:

```bash
php artisan test
```

## Development server

```bash
php artisan serve
npm run dev
```

Then open `http://localhost:8000`.

## Project structure (highlights)

```
app/
├── Http/Controllers/   # Dashboard, Account, Transaction, Budget, SavingsGoal,
│                       # Recurring, Report, Assistant, Google, Preferences
├── Models/             # Eloquent models
├── Policies/           # OwnedByUserPolicy + per-resource policies
├── Services/           # Finance engine (transactions, transfers, budgets, goals,
│                       # recurring, forecast, safe-to-spend, analytics, insights,
│                       # FinancialContextService, LocaleService)
└── Support/Money.php   # Deterministic integer-cent money math
database/
├── migrations/         # 18 migrations
└── seeders/            # DatabaseSeeder (system categories + demo data)
resources/js/
├── Pages/              # Inertia + React pages
├── Layouts/            # App, Guest, Settings layouts
└── components/         # UI + finance components
lang/                   # en.json / fr.json / ar.json
routes/web.php          # Web routes
tests/                  # Pest test suite
```
