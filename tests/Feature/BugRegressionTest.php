<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\RecurringTransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Regression: starting balance is preserved when creating an account via the
| store route (BUG A — initial balance was lost on first recompute).
|--------------------------------------------------------------------------
*/

test('REG-A: an account created via the store route keeps its starting balance after a transaction', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('accounts.store'), [
        'name' => 'Bank',
        'type' => 'bank',
        'balance' => '10000.00',
    ])->assertSessionHasNoErrors();

    $account = $user->accounts()->first();
    // Both the displayed balance and the canonical starting balance are set.
    expect($account->balance)->toBe('10000.00')
        ->and($account->starting_balance)->toBe('10000.00');

    // Income of 2500.75 must add to the 10000 starting balance, not reset to 0.
    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'income',
        'account_id' => $account->id,
        'amount' => '2500.75',
        'date' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    expect($account->fresh()->balance)->toBe('12500.75')
        ->and(app(AccountBalanceService::class)->computeBalance($account))->toBe('12500.75');
});

/*
|--------------------------------------------------------------------------
| Regression: creating an account with no balance stores a zero starting
| balance, and the ledger recompute still yields the correct amounts (BUG A
| must not double-count or break the zero-balance path).
|--------------------------------------------------------------------------
*/

test('REG-A2: an account created without an initial balance records income/expense correctly', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('accounts.store'), [
        'name' => 'Wallet',
        'type' => 'digital_wallet',
    ])->assertSessionHasNoErrors();

    $account = $user->accounts()->first();
    expect($account->starting_balance)->toBe('0.00');

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'expense',
        'account_id' => $account->id,
        'amount' => '49.99',
        'date' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    expect($account->fresh()->balance)->toBe('-49.99');
});

/*
|--------------------------------------------------------------------------
| Regression: recurring transactions without an explicit next_occurrence
| default to the start date (BUG B — NOT NULL column caused a silent failure).
|--------------------------------------------------------------------------
*/

test('REG-B: a recurring transaction created without next_occurrence persists, defaulting to start_date', function () {
    $user = User::factory()->create();
    $account = Account::create(['user_id' => $user->id, 'name' => 'Bank', 'type' => 'bank', 'starting_balance' => '0.00', 'balance' => '0.00']);

    $this->actingAs($user)->post(route('recurring.store'), [
        'name' => 'Subscription',
        'type' => 'expense',
        'amount' => '99.00',
        'account_id' => $account->id,
        'frequency' => 'weekly',
        'interval' => 1,
        'start_date' => '2026-08-01',
    ])->assertSessionHasNoErrors();

    $recurring = $user->recurringTransactions()->first();
    expect($recurring)->not->toBeNull()
        ->and($recurring->next_occurrence->toDateString())->toBe('2026-08-01');

    // It must also be usable by the projection engine (no crash).
    app(RecurringTransactionService::class)->monthlySummaryForUser($user->id, CarbonImmutable::parse('2026-08-01'));
});

/*
|--------------------------------------------------------------------------
| Regression: global default (system) categories are available to a fresh
| user (BUG C — no categories existed, so categorization was impossible).
|--------------------------------------------------------------------------
*/

test('REG-C: default system categories are seeded and visible to a fresh user', function () {
    $user = User::factory()->create();

    $expenseCategories = Category::query()
        ->where(function ($q) use ($user) {
            $q->whereNull('user_id')->orWhere('user_id', $user->id);
        })
        ->where('type', 'expense')
        ->where('is_active', true)
        ->get();

    expect($expenseCategories->count())->toBeGreaterThan(0);

    // They appear in the transaction-form category list exposed by the
    // transactions page (server-driven).
    $this->actingAs($user)->get(route('transactions.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Transactions/Index')
            ->where('categories', fn ($cats) => count($cats) > 0));
});

test('REG-C2: a category budget can be created against a seeded category', function () {
    $user = User::factory()->create();
    $food = Category::where('type', 'expense')->where('is_system', true)->first();

    expect($food)->not->toBeNull();

    $this->actingAs($user)->post(route('budgets.store'), [
        'name' => 'Food',
        'scope' => 'category',
        'category_id' => $food->id,
        'period' => 'monthly',
        'amount' => '300.00',
    ])->assertSessionHasNoErrors();

    expect($user->budgets()->count())->toBe(1);
});
