<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\TransactionService;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function updateAccount(User $user, string $name = 'Checking', string $starting = '100.00'): Account
{
    return Account::create([
        'user_id' => $user->id,
        'name' => $name,
        'type' => 'bank',
        'starting_balance' => $starting,
        'balance' => $starting,
    ]);
}

function updateService(): TransactionService
{
    return app(TransactionService::class);
}

function balanceOf(Account $account): string
{
    return app(AccountBalanceService::class)->computeBalance($account->fresh());
}

test('editing an expense amount updates the account balance', function () {
    $user = User::factory()->create();
    $account = updateAccount($user);
    $tx = updateService()->createExpense($account, ['amount' => '20.00', 'date' => '2025-01-05']);

    expect(balanceOf($account))->toBe('80.00');

    updateService()->update($tx, $account, [
        'type' => 'expense', 'account_id' => $account->id, 'amount' => '45.00', 'date' => '2025-01-05',
    ]);

    expect(balanceOf($account))->toBe('55.00');
});

test('moving an expense to another account moves the effect between balances', function () {
    $user = User::factory()->create();
    $a = updateAccount($user, 'Checking');
    $b = updateAccount($user, 'Savings', '0.00');
    $tx = updateService()->createExpense($a, ['amount' => '30.00', 'date' => '2025-01-05']);

    updateService()->update($tx, $b, [
        'type' => 'expense', 'account_id' => $b->id, 'amount' => '30.00', 'date' => '2025-01-05',
    ]);

    expect(balanceOf($a))->toBe('100.00')
        ->and(balanceOf($b))->toBe('-30.00');
});

test('changing an income to an expense reverses its effect', function () {
    $user = User::factory()->create();
    $account = updateAccount($user);
    $tx = updateService()->createIncome($account, ['amount' => '50.00', 'date' => '2025-01-05']);

    expect(balanceOf($account))->toBe('150.00');

    updateService()->update($tx, $account, [
        'type' => 'expense', 'account_id' => $account->id, 'amount' => '50.00', 'date' => '2025-01-05',
    ]);

    expect(balanceOf($account))->toBe('50.00');
});

test('editing the source of a transfer refreshes both affected accounts', function () {
    $user = User::factory()->create();
    $checking = updateAccount($user, 'Checking', '100.00');
    $savings = updateAccount($user, 'Savings', '0.00');
    $third = updateAccount($user, 'Third', '0.00');

    $tx = app(TransferService::class)->create($checking, $savings, [
        'amount' => '30.00',
        'date' => '2025-01-05',
    ]);

    // Move the transfer source from Checking to Third.
    updateService()->update($tx, $third, [
        'type' => 'transfer', 'account_id' => $third->id,
        'destination_account_id' => $savings->id, 'amount' => '30.00', 'date' => '2025-01-05',
    ]);

    expect(balanceOf($checking))->toBe('100.00')
        ->and(balanceOf($third))->toBe('-30.00')
        ->and(balanceOf($savings))->toBe('30.00');
});

test('editing an income amount still aggregates correctly in analytics and budgets', function () {
    $user = User::factory()->create();
    $account = updateAccount($user);
    $tx = updateService()->createIncome($account, ['amount' => '100.00', 'date' => '2025-01-10']);

    updateService()->update($tx, $account, [
        'type' => 'income', 'account_id' => $account->id, 'amount' => '250.00', 'date' => '2025-01-10',
    ]);

    $analytics = app(\App\Services\FinancialAnalyticsService::class)
        ->summary($user->id, '2025-01-01', '2025-01-31');

    expect($analytics['income'])->toBe('250.00');
});
