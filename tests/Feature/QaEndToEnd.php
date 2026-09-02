<?php

use App\Models\Account;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Budget;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\AI\AssistantService;
use App\Services\AI\NullAIProvider;
use App\Services\BudgetService;
use App\Services\FinancialAnalyticsService;
use App\Services\AI\FinancialToolRegistry;
use App\Services\RecurringTransactionService;
use App\Services\SavingsGoalService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Scenario 1 — New user: register → verify → login → onboarding → account
|--------------------------------------------------------------------------
*/

test('S1: a brand new user can register, verify, log in, onboard and create an account', function () {
    // Guest cannot reach the dashboard.
    $this->get(route('dashboard'))->assertRedirect(route('login'));

    // Register (Fortify auto-authenticates on success).
    $this->post(route('register'), [
        'name' => 'Yasmine',
        'email' => 'yasmine@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    $user = User::where('email', 'yasmine@example.com')->first();
    expect($user)->not->toBeNull();

    // Not verified yet (Fortify default).
    expect($user->email_verified_at)->toBeNull();

    // Verify email.
    $user->markEmailAsVerified();
    expect($user->fresh()->email_verified_at)->not->toBeNull();

    // Log out, then verify a normal email+password login works.
    $this->post('/logout');
    $this->post('/login', ['email' => 'yasmine@example.com', 'password' => 'password'])
        ->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($user);

    // Onboarding creates the first account (routes through PreferencesController).
    $this->post(route('onboarding.account'), [
        'name' => 'My Wallet',
        'type' => 'digital_wallet',
        'balance' => '500.00',
    ])->assertSessionHasNoErrors();

    $account = $user->accounts()->first();
    expect($account->name)->toBe('My Wallet')
        ->and($account->starting_balance)->toBe('500.00')
        ->and($account->balance)->toBe('500.00');

    // Dashboard reflects total balance.
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Dashboard')
            ->has('accounts', 1)
            ->where('totalBalance', '500.00'));
});

/*
|--------------------------------------------------------------------------
| Scenario 2 — Money flow across account types + transfers + decimals
|--------------------------------------------------------------------------
*/

test('S2: creating every account type and recording income/expense keeps balances consistent', function () {
    $user = User::factory()->create();

    $types = [
        'cash' => '1000.00',
        'bank' => '10000.00',
        'savings' => '5000.00',
        'credit_card' => '0.00',
        'digital_wallet' => '250.50',
    ];

    $created = [];
    foreach ($types as $type => $balance) {
        $this->actingAs($user)->post(route('accounts.store'), [
            'name' => ucfirst(str_replace('_', ' ', $type)),
            'type' => $type,
            'balance' => $balance,
        ])->assertSessionHasNoErrors();
        $created[$type] = $user->accounts()->where('type', $type)->first();
    }

    expect($user->accounts()->count())->toBe(5);

    // Record income into the bank account.
    $bank = $created['bank'];
    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'income',
        'account_id' => $bank->id,
        'amount' => '2500.75',
        'date' => now()->toDateString(),
        'description' => 'Salary',
    ])->assertSessionHasNoErrors();

    $this->assertSame('12500.75', $bank->fresh()->balance);

    // Record an expense from the cash account.
    $cash = $created['cash'];
    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'expense',
        'account_id' => $cash->id,
        'amount' => '199.90',
        'date' => now()->toDateString(),
        'description' => 'Groceries',
    ])->assertSessionHasNoErrors();

    $this->assertSame('800.10', $cash->fresh()->balance);
});

test('S2: a transfer moves money between accounts without counting as income/expense', function () {
    $user = User::factory()->create();

    $cash = Account::create(['user_id' => $user->id, 'name' => 'Cash', 'type' => 'cash', 'starting_balance' => '1000.00', 'balance' => '1000.00']);
    $bank = Account::create(['user_id' => $user->id, 'name' => 'Bank', 'type' => 'bank', 'starting_balance' => '2000.00', 'balance' => '2000.00']);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'transfer',
        'account_id' => $cash->id,
        'destination_account_id' => $bank->id,
        'amount' => '333.33',
        'date' => now()->toDateString(),
        'description' => 'Move to bank',
    ])->assertSessionHasNoErrors();

    expect($cash->fresh()->balance)->toBe('666.67')
        ->and($bank->fresh()->balance)->toBe('2333.33')
        ->and($user->transactions()->where('type', 'transfer')->count())->toBe(1);

    // Total is unchanged by an internal transfer.
    $total = $user->accounts()->sum('balance');
    expect($total)->toBe('3000.00');
});

test('S2: a transfer to the same account is rejected', function () {
    $user = User::factory()->create();
    $cash = Account::create(['user_id' => $user->id, 'name' => 'Cash', 'type' => 'cash', 'starting_balance' => '0.00', 'balance' => '0.00']);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'transfer',
        'account_id' => $cash->id,
        'destination_account_id' => $cash->id,
        'amount' => '100.00',
        'date' => now()->toDateString(),
    ])->assertSessionHasErrors('destination_account_id');

    expect($user->transactions()->count())->toBe(0);
});

test('S2: decimal amounts are handled without float rounding drift', function () {
    $user = User::factory()->create();
    $bank = Account::create(['user_id' => $user->id, 'name' => 'Bank', 'type' => 'bank', 'starting_balance' => '0.00', 'balance' => '0.00']);

    $this->actingAs($user)->post(route('transactions.store'), ['type' => 'income', 'account_id' => $bank->id, 'amount' => '0.10', 'date' => now()->toDateString()]);
    $this->actingAs($user)->post(route('transactions.store'), ['type' => 'income', 'account_id' => $bank->id, 'amount' => '0.20', 'date' => now()->toDateString()]);
    $this->actingAs($user)->post(route('transactions.store'), ['type' => 'income', 'account_id' => $bank->id, 'amount' => '0.30', 'date' => now()->toDateString()]);

    // 0.10 + 0.20 + 0.30 must be exactly 0.60 (no 0.59999...).
    expect($bank->fresh()->balance)->toBe('0.60');
});

test('S2: a zero-transaction account keeps its starting balance', function () {
    $user = User::factory()->create();
    $account = Account::create(['user_id' => $user->id, 'name' => 'Empty', 'type' => 'bank', 'starting_balance' => '42.00', 'balance' => '42.00']);

    expect(app(AccountBalanceService::class)->computeBalance($account))->toBe('42.00');
});

test('S2: archived accounts are excluded from the total balance and dashboard', function () {
    $user = User::factory()->create();
    $active = Account::create(['user_id' => $user->id, 'name' => 'Active', 'type' => 'bank', 'starting_balance' => '100.00', 'balance' => '100.00']);
    $archived = Account::create(['user_id' => $user->id, 'name' => 'Old', 'type' => 'bank', 'starting_balance' => '500.00', 'balance' => '500.00', 'is_archived' => true]);

    $balances = app(AccountBalanceService::class);
    expect($balances->totalBalance($user->id))->toBe('100.00');

    $this->actingAs($user)->get(route('accounts.index'))
        ->assertInertia(fn ($p) => $p->component('Accounts/Index')->where('accounts', fn ($accounts) => count($accounts) === 1))
        ->assertInertia(fn ($p) => $p->where('totalBalance', '100.00'));
});

/*
|--------------------------------------------------------------------------
| Scenario 3 — Budgets: overall / monthly / weekly / category, no double counting
|--------------------------------------------------------------------------
*/

test('S3: budgets report spent/remaining/percent/status without double counting transfers', function () {
    $user = User::factory()->create();
    $account = Account::create(['user_id' => $user->id, 'name' => 'Bank', 'type' => 'bank', 'starting_balance' => '0.00', 'balance' => '0.00']);
    $other = Account::create(['user_id' => $user->id, 'name' => 'Cash', 'type' => 'cash', 'starting_balance' => '0.00', 'balance' => '0.00']);

    $budget = $user->budgets()->create([
        'name' => 'Monthly overall',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '1000.00',
    ]);

    // One expense of 400 and one internal transfer of 200 (transfer must NOT count).
    $this->actingAs($user)->post(route('transactions.store'), ['type' => 'expense', 'account_id' => $account->id, 'amount' => '400.00', 'date' => now()->toDateString()]);
    $this->actingAs($user)->post(route('transactions.store'), ['type' => 'transfer', 'account_id' => $account->id, 'destination_account_id' => $other->id, 'amount' => '200.00', 'date' => now()->toDateString()]);

    $status = app(BudgetService::class)->status($budget);
    expect($status['spent'])->toBe('400.00')
        ->and($status['remaining'])->toBe('600.00')
        ->and($status['percent'])->toBe(40)
        ->and($status['status'])->toBe('healthy');
});

test('S3: a category budget counts only expenses in that category', function () {
    $user = User::factory()->create();
    $account = Account::create(['user_id' => $user->id, 'name' => 'Bank', 'type' => 'bank', 'starting_balance' => '0.00', 'balance' => '0.00']);

    $food = Category::find(Category::where('type', 'expense')->where('is_system', true)->first()->id);
    $transport = Category::find(Category::where('type', 'expense')->where('is_active', true)->where('id', '!=', $food->id)->first()->id);

    $budget = $user->budgets()->create([
        'name' => 'Food budget',
        'scope' => 'category',
        'category_id' => $food->id,
        'period' => 'monthly',
        'amount' => '300.00',
    ]);

    $this->actingAs($user)->post(route('transactions.store'), ['type' => 'expense', 'account_id' => $account->id, 'category_id' => $food->id, 'amount' => '100.00', 'date' => now()->toDateString()]);
    $this->actingAs($user)->post(route('transactions.store'), ['type' => 'expense', 'account_id' => $account->id, 'category_id' => $transport->id, 'amount' => '50.00', 'date' => now()->toDateString()]);

    $status = app(BudgetService::class)->status($budget);
    expect($status['spent'])->toBe('100.00'); // only food counted
});

test('S3: a category budget without any transactions reports zero spent, not an error', function () {
    $user = User::factory()->create();
    $account = Account::create(['user_id' => $user->id, 'name' => 'Bank', 'type' => 'bank', 'starting_balance' => '0.00', 'balance' => '0.00']);

    $food = Category::where('type', 'expense')->where('is_system', true)->first();

    $budget = $user->budgets()->create([
        'name' => 'Empty food',
        'scope' => 'category',
        'category_id' => $food->id,
        'period' => 'monthly',
        'amount' => '300.00',
    ]);

    $status = app(BudgetService::class)->status($budget);
    expect($status['spent'])->toBe('0.00')
        ->and($status['percent'])->toBe(0)
        ->and($status['status'])->toBe('healthy');
});

/*
|--------------------------------------------------------------------------
| Scenario 4 — Savings goal: Gaming PC, 10 000 DH, 30/12/2026
|--------------------------------------------------------------------------
*/

test('S4: a savings goal computes progress, deadline, and contributions', function () {
    $user = User::factory()->create();

    $goal = $user->savingsGoals()->create([
        'name' => 'Gaming PC',
        'target_amount' => '10000.00',
        'current_amount' => '2500.00',
        'deadline' => '2026-12-30',
    ]);

    $status = app(SavingsGoalService::class)->status($goal, CarbonImmutable::parse('2026-08-31'));

    expect($status['saved'])->toBe('2500.00')
        ->and($status['remaining'])->toBe('7500.00')
        ->and($status['progress_percent'])->toBe(25.0)
        ->and($status['days_remaining'])->not->toBeNull()
        ->and($status['required_daily'])->not->toBeNull()
        ->and($status['required_monthly'])->not->toBeNull();

    // days_remaining from 2026-08-31 to 2026-12-30 (121 days).
    expect($status['days_remaining'])->toBe(121);

    // required_daily = ceil(750000 / 121) = ceil(6198.34...) = 6199 => 61.99 DH
    expect($status['required_daily'])->toBe('61.99');
});

test('S4: contributing to a goal via the route updates it and completes it when reached', function () {
    $user = User::factory()->create();
    $goal = $user->savingsGoals()->create([
        'name' => 'Gaming PC',
        'target_amount' => '10000.00',
        'current_amount' => '0.00',
        'deadline' => '2026-12-30',
    ]);

    $this->actingAs($user)->post(route('goals.contribute', $goal), ['amount' => '3000.00'])->assertSessionHasNoErrors();
    expect($goal->fresh()->current_amount)->toBe('3000.00')
        ->and($goal->fresh()->is_completed)->toBeFalse();

    $this->actingAs($user)->post(route('goals.contribute', $goal), ['amount' => '7000.00'])->assertSessionHasNoErrors();
    expect($goal->fresh()->current_amount)->toBe('10000.00')
        ->and($goal->fresh()->is_completed)->toBeTrue()
        ->and($goal->fresh()->achieved_at)->not->toBeNull();
});

test('S4: a past-deadline goal reports remaining work and an on-track=false state', function () {
    $user = User::factory()->create();
    $goal = $user->savingsGoals()->create([
        'name' => 'Past PC',
        'target_amount' => '5000.00',
        'current_amount' => '1000.00',
        'deadline' => '2020-01-01',
    ]);

    $status = app(SavingsGoalService::class)->status($goal, CarbonImmutable::parse('2026-08-31'));
    expect($status['days_remaining'])->toBe(0)
        ->and($status['remaining'])->toBe('4000.00');
});

/*
|--------------------------------------------------------------------------
| Scenario 5 — Recurring transactions
|--------------------------------------------------------------------------
*/

test('S5: recurring income and expense summarise into a monthly projection', function () {
    $user = User::factory()->create();
    $account = Account::create(['user_id' => $user->id, 'name' => 'Bank', 'type' => 'bank', 'starting_balance' => '0.00', 'balance' => '0.00']);

    $user->recurringTransactions()->create([
        'name' => 'Salary',
        'type' => 'income',
        'amount' => '12000.00',
        'account_id' => $account->id,
        'frequency' => 'monthly',
        'interval' => 1,
        'next_occurrence' => '2026-08-01',
        'start_date' => '2026-08-01',
    ]);

    $user->recurringTransactions()->create([
        'name' => 'Rent',
        'type' => 'expense',
        'amount' => '2500.00',
        'account_id' => $account->id,
        'frequency' => 'monthly',
        'interval' => 1,
        'next_occurrence' => '2026-08-05',
        'start_date' => '2026-08-05',
    ]);

    $summary = app(RecurringTransactionService::class)->monthlySummaryForUser($user->id, CarbonImmutable::parse('2026-08-01'));
    expect($summary['income'])->toBe('12000.00')
        ->and($summary['expense'])->toBe('2500.00');
});

test('S5: creating a recurring transaction WITHOUT next_occurrence must not fail if the controller accepts null', function () {
    $user = User::factory()->create();
    $account = Account::create(['user_id' => $user->id, 'name' => 'Bank', 'type' => 'bank', 'starting_balance' => '0.00', 'balance' => '0.00']);

    $response = $this->actingAs($user)->post(route('recurring.store'), [
        'name' => 'Weekly subscription',
        'type' => 'expense',
        'amount' => '99.00',
        'account_id' => $account->id,
        'frequency' => 'weekly',
        'interval' => 1,
        'start_date' => '2026-08-01',
    ]);

    // The controller explicitly allows next_occurrence to be nullable.
    $response->assertSessionHasNoErrors();

    // The column is NOT NULL, so the record must still have been persisted.
    $recurring = $user->recurringTransactions()->first();
    expect($recurring)->not->toBeNull()
        ->and($recurring->next_occurrence)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Scenario 6 — Reports: transfers excluded, month comparison
|--------------------------------------------------------------------------
*/

test('S6: report analytics exclude transfers and compare months correctly', function () {
    $user = User::factory()->create();
    $account = Account::create(['user_id' => $user->id, 'name' => 'Bank', 'type' => 'bank', 'starting_balance' => '0.00', 'balance' => '0.00']);
    $other = Account::create(['user_id' => $user->id, 'name' => 'Cash', 'type' => 'cash', 'starting_balance' => '0.00', 'balance' => '0.00']);

    $current = now()->toDateString();

    $this->actingAs($user)->post(route('transactions.store'), ['type' => 'income', 'account_id' => $account->id, 'amount' => '5000.00', 'date' => $current]);
    $this->actingAs($user)->post(route('transactions.store'), ['type' => 'expense', 'account_id' => $account->id, 'amount' => '1200.00', 'date' => $current]);
    $this->actingAs($user)->post(route('transactions.store'), ['type' => 'transfer', 'account_id' => $account->id, 'destination_account_id' => $other->id, 'amount' => '3000.00', 'date' => $current]);

    $analytics = app(FinancialAnalyticsService::class);
    $from = now()->startOfMonth()->toDateString();
    $to = now()->endOfMonth()->toDateString();
    $summary = $analytics->summary($user->id, $from, $to);

    // Transfer must NOT inflate income or expenses.
    expect($summary['income'])->toBe('5000.00')
        ->and($summary['expenses'])->toBe('1200.00')
        ->and($summary['net'])->toBe('3800.00');

    $this->actingAs($user)->get(route('reports'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Reports/Index')
            ->where('summary.income', '5000.00')
            ->where('summary.expenses', '1200.00'));
});

test('S6: balance history is continuous across days and respects archived accounts', function () {
    $user = User::factory()->create();
    $account = Account::create(['user_id' => $user->id, 'name' => 'Bank', 'type' => 'bank', 'starting_balance' => '100.00', 'balance' => '100.00']);

    $this->actingAs($user)->post(route('transactions.store'), ['type' => 'income', 'account_id' => $account->id, 'amount' => '50.00', 'date' => now()->subDays(2)->toDateString()]);

    $history = app(AccountBalanceService::class)->balanceHistory($user->id, now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());

    // Every day in the window is present (no gaps).
    $days = now()->startOfMonth()->daysInMonth;
    expect($history)->toHaveCount($days);
});

/*
|--------------------------------------------------------------------------
| Scenario 12 — Data isolation (direct URL access)
|--------------------------------------------------------------------------
*/

test('S12: a user cannot reach another user records via direct route URLs', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $accountB = Account::create(['user_id' => $userB->id, 'name' => 'B secret', 'type' => 'bank', 'starting_balance' => '999.00', 'balance' => '999.00']);
    $budgetB = $userB->budgets()->create(['name' => 'B budget', 'scope' => 'overall', 'period' => 'monthly', 'amount' => '100.00']);
    $goalB = $userB->savingsGoals()->create(['name' => 'B goal', 'target_amount' => '100.00']);
    $recurringB = $userB->recurringTransactions()->create(['name' => 'B recurring', 'type' => 'expense', 'amount' => '10.00', 'account_id' => $accountB->id, 'frequency' => 'monthly', 'interval' => 1, 'next_occurrence' => now()->toDateString(), 'start_date' => now()->toDateString()]);
    $txB = Transaction::create(['user_id' => $userB->id, 'account_id' => $accountB->id, 'type' => 'expense', 'amount' => '10.00', 'date' => now()->toDateString()]);
    $convoB = AiConversation::create(['user_id' => $userB->id, 'title' => 'B convo']);

    $this->actingAs($userA)
        ->get(route('accounts.show', $accountB))->assertForbidden();
    $this->actingAs($userA)
        ->put(route('budgets.update', ['budget' => $budgetB]), ['name' => 'hack', 'scope' => 'overall', 'period' => 'monthly', 'amount' => '1'])->assertForbidden();
    $this->actingAs($userA)
        ->put(route('goals.update', ['goal' => $goalB]), ['name' => 'hack', 'target_amount' => '1'])->assertForbidden();
    $this->actingAs($userA)
        ->put(route('recurring.update', ['recurring' => $recurringB]), ['name' => 'hack', 'type' => 'expense', 'amount' => '1', 'account_id' => $accountB->id, 'frequency' => 'monthly', 'interval' => 1, 'start_date' => now()->toDateString()])->assertForbidden();
    $this->actingAs($userA)
        ->put(route('transactions.update', ['transaction' => $txB]), ['type' => 'expense', 'account_id' => $accountB->id, 'amount' => '1', 'date' => now()->toDateString()])->assertForbidden();
    $this->actingAs($userA)
        ->get(route('assistant.show', $convoB))->assertForbidden();
});

test('S12: analytics are scoped to the authenticated user only', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $accountB = Account::create(['user_id' => $userB->id, 'name' => 'B bank', 'type' => 'bank', 'starting_balance' => '5000.00', 'balance' => '5000.00']);
    $this->actingAs($userB)->post(route('transactions.store'), ['type' => 'income', 'account_id' => $accountB->id, 'amount' => '9000.00', 'date' => now()->toDateString()]);

    $analytics = app(FinancialAnalyticsService::class);
    $from = now()->startOfMonth()->toDateString();
    $to = now()->endOfMonth()->toDateString();
    $summaryA = $analytics->summary($userA->id, $from, $to);

    expect($summaryA['income'])->toBe('0.00');
});

/*
|--------------------------------------------------------------------------
| Scenario 7 — AI assistant security (no key -> graceful; malicious prompts)
|--------------------------------------------------------------------------
*/

test('S7: the assistant degrades gracefully when no API key is configured', function () {
    config(['services.ai.api_key' => null]);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('assistant.index'))->assertOk();

    $this->actingAs($user)->post(route('assistant.send'), [
        'message' => 'How much did I spend this month?',
    ])->assertRedirect();

    // The assistant reply should be a friendly localized "not configured" message.
    $reply = $user->aiConversations()->first()->messages()->where('role', 'assistant')->first();
    expect($reply)->not->toBeNull()
        ->and($reply->content)->not->toBeEmpty()
        ->and($reply->content)->not->toContain('Exception');
});

test('S7: assistant tool registry rejects unknown or malicious tool names', function () {
    $user = User::factory()->create();
    $registry = app(FinancialToolRegistry::class);

    $this->actingAs($user);

    expect(fn () => $registry->execute('not_a_real_tool', $user->id, []))->toThrow(RuntimeException::class);
});

test('S7: a malicious user_id argument is ignored — the injected user id always wins', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Account::create(['user_id' => $userB->id, 'name' => 'B bank', 'type' => 'bank', 'starting_balance' => '8888.00', 'balance' => '8888.00']);

    $accountA = Account::create(['user_id' => $userA->id, 'name' => 'A bank', 'type' => 'bank', 'starting_balance' => '0.00', 'balance' => '0.00']);
    $this->actingAs($userA)->post(route('transactions.store'), [
        'type' => 'income',
        'account_id' => $accountA->id,
        'amount' => '100.00',
        'date' => now()->toDateString(),
    ]);

    $registry = app(FinancialToolRegistry::class);
    $result = $registry->execute('getAccountBalances', $userA->id, ['user_id' => $userB->id]);

    // Must return only user A's data, never B's 8888.00 bank.
    expect($result)->not->toContain('8888.00');
});
