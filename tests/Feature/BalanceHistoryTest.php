<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function balanceAccount(User $user): Account
{
    return Account::create([
        'user_id' => $user->id,
        'name' => 'Checking',
        'type' => 'bank',
        'starting_balance' => '100.00',
        'balance' => '100.00',
    ]);
}

test('balance history extends through the end of the range even when no transaction falls there', function () {
    $user = User::factory()->create();
    $account = balanceAccount($user);

    $ts = app(TransactionService::class);
    $ts->createIncome($account, ['amount' => '50.00', 'date' => '2025-01-05']);
    $ts->createExpense($account, ['amount' => '20.00', 'date' => '2025-01-07']);

    $history = app(AccountBalanceService::class)
        ->balanceHistory($user->id, '2025-01-01', '2025-01-10')
        ->mapWithKeys(fn ($d) => [$d['date'] => $d['balance']]);

    // Balance on the trailing days (after the last transaction) is 130.00.
    expect($history['2025-01-08'] ?? null)->toBe('130.00')
        ->and($history['2025-01-10'] ?? null)->toBe('130.00')
        ->and($history['2025-01-05'] ?? null)->toBe('150.00')
        ->and($history['2025-01-07'] ?? null)->toBe('130.00');
});

test('balance history start reflects transactions dated before the range', function () {
    $user = User::factory()->create();
    $account = balanceAccount($user);

    $ts = app(TransactionService::class);
    $ts->createIncome($account, ['amount' => '50.00', 'date' => '2024-12-20']);

    $history = app(AccountBalanceService::class)
        ->balanceHistory($user->id, '2025-01-01', '2025-01-05')
        ->mapWithKeys(fn ($d) => [$d['date'] => $d['balance']]);

    // January 1st balance should already include the December income (150.00).
    expect($history['2025-01-01'] ?? null)->toBe('150.00');
});

test('a range with no transactions at all returns the starting balance on every day', function () {
    $user = User::factory()->create();
    balanceAccount($user);

    $history = app(AccountBalanceService::class)
        ->balanceHistory($user->id, '2025-03-01', '2025-03-03')
        ->mapWithKeys(fn ($d) => [$d['date'] => $d['balance']]);

    expect($history['2025-03-01'] ?? null)->toBe('100.00')
        ->and($history['2025-03-02'] ?? null)->toBe('100.00')
        ->and($history['2025-03-03'] ?? null)->toBe('100.00');
});

test('decimal amounts are honoured to the cent', function () {
    $user = User::factory()->create();
    $account = balanceAccount($user);

    $ts = app(TransactionService::class);
    $ts->createIncome($account, ['amount' => '12.34', 'date' => '2025-01-02']);
    $ts->createExpense($account, ['amount' => '7.89', 'date' => '2025-01-03']);

    $history = app(AccountBalanceService::class)
        ->balanceHistory($user->id, '2025-01-01', '2025-01-04')
        ->mapWithKeys(fn ($d) => [$d['date'] => $d['balance']]);

    // 100.00 + 12.34 = 112.34, then - 7.89 = 104.45.
    expect($history['2025-01-02'] ?? null)->toBe('112.34')
        ->and($history['2025-01-03'] ?? null)->toBe('104.45')
        ->and($history['2025-01-04'] ?? null)->toBe('104.45');
});

test('history across multiple accounts aggregates their starting balances and activity', function () {
    $user = User::factory()->create();
    Account::create([
        'user_id' => $user->id,
        'name' => 'Checking',
        'type' => 'bank',
        'starting_balance' => '250.50',
        'balance' => '250.50',
    ]);
    $savings = Account::create([
        'user_id' => $user->id,
        'name' => 'Savings',
        'type' => 'savings',
        'starting_balance' => '49.50',
        'balance' => '49.50',
    ]);

    $ts = app(TransactionService::class);
    $ts->createIncome($savings, ['amount' => '10.00', 'date' => '2025-01-05']);

    $history = app(AccountBalanceService::class)
        ->balanceHistory($user->id, '2025-01-01', '2025-01-06')
        ->mapWithKeys(fn ($d) => [$d['date'] => $d['balance']]);

    // Combined starting balance 250.50 + 49.50 = 300.00, + 10.00 on the 5th.
    expect($history['2025-01-01'] ?? null)->toBe('300.00')
        ->and($history['2025-01-05'] ?? null)->toBe('310.00')
        ->and($history['2025-01-06'] ?? null)->toBe('310.00');
});

test('transfer history moves money without changing the total', function () {
    $user = User::factory()->create();
    $checking = balanceAccount($user);
    $savings = Account::create([
        'user_id' => $user->id,
        'name' => 'Savings',
        'type' => 'savings',
        'starting_balance' => '0.00',
        'balance' => '0.00',
    ]);

    app(\App\Services\TransferService::class)->create($checking, $savings, [
        'amount' => '30.00',
        'date' => '2025-01-06',
    ]);

    $history = app(AccountBalanceService::class)
        ->balanceHistory($user->id, '2025-01-01', '2025-01-10')
        ->mapWithKeys(fn ($d) => [$d['date'] => $d['balance']]);

    // Total across both accounts stays 100.00 throughout.
    expect($history['2025-01-06'] ?? null)->toBe('100.00')
        ->and($history['2025-01-01'] ?? null)->toBe('100.00');
});
