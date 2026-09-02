<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\BudgetService;
use App\Services\SafeToSpendService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A budget is ONLY a spending plan/limit. Creating, modifying or deleting a
 * budget never reserves, deducts, locks or subtracts money from Safe to Spend.
 * Safe to Spend is reduced only by real transactions (e.g. an expense).
 */
function budgetSafeSpendUser(): array
{
    $user = User::factory()->create();
    $account = Account::create([
        'user_id' => $user->id,
        'name' => 'Checking',
        'type' => 'bank',
        'starting_balance' => '808.00',
        'balance' => '808.00',
    ]);

    return [$user, $account];
}

function safeToSpendValue(int $userId): float
{
    return (float) app(SafeToSpendService::class)->safeToSpend($userId)['safe_to_spend'];
}

function totalBalanceValue(int $userId): float
{
    return (float) app(AccountBalanceService::class)->totalBalance($userId);
}

test('A) with no budgets the full balance is safe to spend', function () {
    [$user] = budgetSafeSpendUser();

    expect(totalBalanceValue($user->id))->toBe(808.0)
        ->and(safeToSpendValue($user->id))->toBe(808.0);
});

test('B) creating an active budget never reduces safe to spend or balance', function () {
    [$user] = budgetSafeSpendUser();

    $this->actingAs($user)->post(route('budgets.store'), [
        'name' => 'Groceries',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '400.00',
    ])->assertRedirect();

    $budget = $user->budgets()->firstOrFail();
    $status = app(BudgetService::class)->status($budget);

    expect($status['remaining'])->toBe('400.00')
        ->and(totalBalanceValue($user->id))->toBe(808.0)
        ->and(safeToSpendValue($user->id))->toBe(808.0);
});

test('C) spending from a budget reduces balance, spent and safe to spend', function () {
    [$user, $account] = budgetSafeSpendUser();

    $budget = $user->budgets()->create([
        'name' => 'Groceries',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '400.00',
    ]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'expense',
        'account_id' => $account->id,
        'amount' => '100.00',
        'date' => now()->toDateString(),
    ])->assertRedirect();

    $status = app(BudgetService::class)->status($budget);

    expect((float) app(AccountBalanceService::class)->computeBalance($account->fresh()))->toBe(708.0)
        ->and(totalBalanceValue($user->id))->toBe(708.0)
        ->and(safeToSpendValue($user->id))->toBe(708.0)
        ->and($status['spent'])->toBe('100.00')
        ->and($status['remaining'])->toBe('300.00');
});

test('D) deleting a budget after spending keeps the expense and safe to spend', function () {
    [$user, $account] = budgetSafeSpendUser();

    $budget = $user->budgets()->create([
        'name' => 'Groceries',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '400.00',
    ]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'expense',
        'account_id' => $account->id,
        'amount' => '100.00',
        'date' => now()->toDateString(),
    ])->assertRedirect();

    $this->actingAs($user)->delete(route('budgets.destroy', $budget))->assertRedirect();

    expect(totalBalanceValue($user->id))->toBe(708.0)
        ->and(safeToSpendValue($user->id))->toBe(708.0)
        ->and(Transaction::where('user_id', $user->id)->count())->toBe(1);
});

test('E) creating then deleting a budget alone never changes balance or safe to spend', function () {
    [$user] = budgetSafeSpendUser();

    expect(totalBalanceValue($user->id))->toBe(808.0)
        ->and(safeToSpendValue($user->id))->toBe(808.0);

    $budget = $user->budgets()->create([
        'name' => 'Groceries',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '400.00',
    ]);

    expect(totalBalanceValue($user->id))->toBe(808.0)
        ->and(safeToSpendValue($user->id))->toBe(808.0);

    $this->actingAs($user)->delete(route('budgets.destroy', $budget))->assertRedirect();

    expect(totalBalanceValue($user->id))->toBe(808.0)
        ->and(safeToSpendValue($user->id))->toBe(808.0)
        ->and(Transaction::where('user_id', $user->id)->count())->toBe(0);
});

test('multiple active budgets never reduce safe to spend', function () {
    [$user] = budgetSafeSpendUser();

    foreach ([['name' => 'A', 'amount' => '100.00'], ['name' => 'B', 'amount' => '200.00']] as $b) {
        $user->budgets()->create([
            'name' => $b['name'],
            'scope' => 'overall',
            'period' => 'monthly',
            'amount' => $b['amount'],
        ]);
    }

    expect(totalBalanceValue($user->id))->toBe(808.0)
        ->and(safeToSpendValue($user->id))->toBe(808.0);
});

test('safe to spend response no longer exposes reserved_by_budgets', function () {
    [$user] = budgetSafeSpendUser();

    $safe = app(SafeToSpendService::class)->safeToSpend($user->id);

    expect($safe)->not->toHaveKey('reserved_by_budgets');
});
