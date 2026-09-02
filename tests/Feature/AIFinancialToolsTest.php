<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AI\FinancialToolRegistry;
use App\Services\TransactionService;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function aiUserWithAccounts(): array
{
    $user = User::factory()->create(['locale' => 'en', 'currency' => 'MAD']);

    $checking = Account::create([
        'user_id' => $user->id,
        'name' => 'Checking',
        'type' => 'bank',
        'starting_balance' => '1000.00',
        'balance' => '1000.00',
    ]);

    $savings = Account::create([
        'user_id' => $user->id,
        'name' => 'Savings',
        'type' => 'savings',
        'starting_balance' => '500.00',
        'balance' => '500.00',
    ]);

    return [$user, $checking, $savings];
}

test('getFinancialSummary reports income and expenses and excludes transfers', function () {
    [$user, $checking, $savings] = aiUserWithAccounts();

    $food = Category::create(['user_id' => $user->id, 'name' => 'Food', 'slug' => 'food', 'type' => 'expense']);

    app(TransactionService::class)->createIncome($checking, [
        'amount' => '2000.00', 'description' => 'Salary', 'date' => now()->toDateString(),
    ]);
    app(TransactionService::class)->createExpense($checking, [
        'amount' => '400.00', 'description' => 'Groceries', 'date' => now()->toDateString(), 'category_id' => $food->id,
    ]);
    // A transfer must never be counted as income or spending.
    app(TransferService::class)->create($checking, $savings, [
        'amount' => '100.00', 'date' => now()->toDateString(),
    ]);

    $result = app(FinancialToolRegistry::class)->execute('getFinancialSummary', $user->id);

    expect($result['income'])->toBe(2000.0)
        ->and($result['expenses'])->toBe(400.0)
        ->and($result['net'])->toBe(1600.0);
});

test('tool execution is strictly scoped to the authenticated user', function () {
    [$userA, $aChecking, ] = aiUserWithAccounts();
    [$userB, $bChecking, ] = aiUserWithAccounts();

    app(TransactionService::class)->createIncome($aChecking, [
        'amount' => '111.00', 'description' => 'A-only', 'date' => now()->toDateString(),
    ]);
    app(TransactionService::class)->createIncome($bChecking, [
        'amount' => '9999.00', 'description' => 'B-only', 'date' => now()->toDateString(),
    ]);

    $result = app(FinancialToolRegistry::class)->execute('getAccountBalances', $userA->id);

    // User A never sees any of user B's balance.
    expect((float) $result['total_balance'])->not->toBe(9999.0 + (float) $bChecking->starting_balance)
        ->and($result['accounts'])->not->toBeEmpty();
});

test('tool user id is injected, never read from arguments', function () {
    [$userA, , ] = aiUserWithAccounts();

    // A malicious "user_id" argument is ignored in favour of the injected user.
    $result = app(FinancialToolRegistry::class)->execute(
        'getAccountBalances',
        $userA->id,
        ['user_id' => 999999]
    );

    expect($result)->toBeArray();
});

test('unknown tool name is rejected by the registry', function () {
    $user = User::factory()->create();

    expect(fn () => app(FinancialToolRegistry::class)->execute('dropDatabase', $user->id))
        ->toThrow(RuntimeException::class);
});

test('getSafeToSpend returns deterministic values', function () {
    [$user, $checking, $savings] = aiUserWithAccounts();

    $result = app(FinancialToolRegistry::class)->execute('getSafeToSpend', $user->id);

    expect($result)->toHaveKeys(['total_safe_to_spend', 'daily_allowance', 'period_end'])
        ->and($result['currency'])->toBe('MAD')
        ->and($result['total_safe_to_spend'])->toBeNumeric();
});

test('getSafeToSpend is not reduced by a goal required monthly contribution', function () {
    [$user, $checking, $savings] = aiUserWithAccounts();

    // Balance across both accounts is 1500. A goal with zero saved and a future
    // deadline yields a positive required monthly amount.
    SavingsGoal::create([
        'user_id' => $user->id,
        'name' => 'sns',
        'target_amount' => '800.00',
        'current_amount' => '0.00',
        'deadline' => now()->addMonths(5)->toDateString(),
    ]);

    $result = app(FinancialToolRegistry::class)->execute('getSafeToSpend', $user->id);

    // The recommendation is not committed money, so it must not reduce the
    // amount the AI reports as safely spendable.
    expect((float) $result['total_safe_to_spend'])->toBe(1500.0)
        ->and((float) $result['planned_savings'])->toBe(0.0);
});

test('getBudgetStatus reports spending percent and status', function () {
    $user = User::factory()->create();

    Budget::create([
        'user_id' => $user->id,
        'name' => 'Food',
        'amount' => '500.00',
        'period' => 'monthly',
        'scope' => 'global',
        'start_date' => now()->startOfMonth()->toDateString(),
    ]);

    $result = app(FinancialToolRegistry::class)->execute('getBudgetStatus', $user->id);

    expect($result['budgets'])->toHaveCount(1)
        ->and($result['budgets'][0]['name'])->toBe('Food')
        ->and($result['budgets'][0]['status'])->toBeString();
});

test('getSavingsGoalStatus reports progress and required saving', function () {
    $user = User::factory()->create();

    SavingsGoal::create([
        'user_id' => $user->id,
        'name' => 'Gaming PC',
        'target_amount' => '10000.00',
        'current_amount' => '1000.00',
        'deadline' => now()->addMonths(10)->toDateString(),
    ]);

    $result = app(FinancialToolRegistry::class)->execute('getSavingsGoalStatus', $user->id);

    expect($result['savings_goals'])->toHaveCount(1)
        ->and($result['savings_goals'][0]['name'])->toBe('Gaming PC')
        ->and($result['savings_goals'][0]['progress_percent'])->toBe(10.0)
        ->and($result['savings_goals'][0]['required_monthly'])->toBeGreaterThan(0);
});

test('getCategorySpending returns the largest categories', function () {
    [$user, $checking, ] = aiUserWithAccounts();

    $food = Category::create(['user_id' => $user->id, 'name' => 'Food', 'slug' => 'food', 'type' => 'expense']);
    $rent = Category::create(['user_id' => $user->id, 'name' => 'Rent', 'slug' => 'rent', 'type' => 'expense']);

    app(TransactionService::class)->createExpense($checking, [
        'amount' => '200.00', 'description' => 'Food', 'date' => now()->toDateString(), 'category_id' => $food->id,
    ]);
    app(TransactionService::class)->createExpense($checking, [
        'amount' => '800.00', 'description' => 'Rent', 'date' => now()->toDateString(), 'category_id' => $rent->id,
    ]);

    $result = app(FinancialToolRegistry::class)->execute('getCategorySpending', $user->id);

    expect($result['categories'][0]['name'])->toBe('Rent')
        ->and($result['categories'][0]['spent'])->toBe(800.0)
        ->and($result['categories'][1]['name'])->toBe('Food');
});

test('getForecast returns a projected balance', function () {
    [$user, $checking, ] = aiUserWithAccounts();

    $result = app(FinancialToolRegistry::class)->execute('getForecast', $user->id);

    expect($result)->toHaveKeys(['current_balance', 'expected_income', 'expected_expenses', 'projected_balance'])
        ->and($result['projected_balance'])->toBeNumeric();
});

test('compareMonths compares this month to last month', function () {
    [$user, $checking, ] = aiUserWithAccounts();

    $food = Category::create(['user_id' => $user->id, 'name' => 'Food', 'slug' => 'food', 'type' => 'expense']);

    // Last month: 100 food. This month: 300 food.
    $lastMonth = now()->subMonth();
    app(TransactionService::class)->createExpense($checking, [
        'amount' => '100.00', 'description' => 'Food', 'date' => $lastMonth->toDateString(), 'category_id' => $food->id,
    ]);
    app(TransactionService::class)->createExpense($checking, [
        'amount' => '300.00', 'description' => 'Food', 'date' => now()->toDateString(), 'category_id' => $food->id,
    ]);

    $result = app(FinancialToolRegistry::class)->execute('compareMonths', $user->id);

    expect($result)->toHaveKeys(['income_delta_percent', 'expense_delta_percent', 'category_comparison'])
        ->and($result['expense_delta_percent'])->toBe(200.0);
});

test('getRecentTransactions returns only the user own transactions', function () {
    [$userA, $checking, ] = aiUserWithAccounts();

    app(TransactionService::class)->createExpense($checking, [
        'amount' => '50.00', 'description' => 'Coffee', 'date' => now()->toDateString(),
    ]);

    $result = app(FinancialToolRegistry::class)->execute('getRecentTransactions', $userA->id);

    expect($result['transactions'])->toHaveCount(1)
        ->and($result['transactions'][0]['amount'])->toBe(50.0);
});

test('getAccountBalances reports the ledger-derived balance, never the starting balance', function () {
    // Regression: the tool must load `user_id` on the accounts it passes to
    // computeBalance(), otherwise the ledger queries are filtered by user_id=0
    // (unloaded attribute -> null -> 0) and every account silently falls back to
    // its starting_balance. That produced "Cash 600, Bank 100, Total 708": the
    // per-account lines used starting_balance (tjr=100) while the total read the
    // stored balance column (600+108=708), so the total used 108 but the bank
    // line showed 100.
    $user = User::factory()->create(['locale' => 'en', 'currency' => 'MAD']);

    $cash = Account::create([
        'user_id' => $user->id,
        'name' => 'Cash',
        'type' => 'cash',
        'starting_balance' => '600.00',
        'balance' => '600.00',
    ]);

    $bank = Account::create([
        'user_id' => $user->id,
        'name' => 'Bank',
        'type' => 'bank',
        'starting_balance' => '100.00',
        'balance' => '108.00',
    ]);

    // The bank account has a +8 income on the ledger, so its true balance is 108,
    // while its starting_balance is 100. The cash account has no net activity.
    app(TransactionService::class)->createIncome($bank, [
        'amount' => '8.00', 'description' => 'Interest', 'date' => now()->toDateString(),
    ]);

    $result = app(FinancialToolRegistry::class)->execute('getAccountBalances', $user->id);

    $byName = collect($result['accounts'])->keyBy('name');

    // The bank account must report exactly 108 (the ledger-derived balance), NOT
    // its starting_balance of 100. No rounding/truncation/casting may change it.
    expect((float) $byName['Bank']['balance'])->toBe(108.0)
        ->and((string) $byName['Bank']['balance'])->NOT->toContain('100');
    expect((float) $byName['Cash']['balance'])->toBe(600.0);

    // Total uses the ledger-derived balances too.
    expect((float) $result['total_balance'])->toBe(708.0);
});
