<?php

use App\Models\Account;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\FinancialAnalyticsService;
use App\Services\TransactionService;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function archAccount(User $user, string $name, string $starting, bool $archived): Account
{
    return Account::create([
        'user_id' => $user->id,
        'name' => $name,
        'type' => 'bank',
        'starting_balance' => $starting,
        'balance' => $starting,
        'is_archived' => $archived,
    ]);
}

function activeAggregate(User $user, string $from, string $to): array
{
    return app(AccountBalanceService::class)
        ->balanceHistory($user->id, $from, $to)
        ->mapWithKeys(fn ($d) => [$d['date'] => $d['balance']])
        ->all();
}

test('archived account is excluded from the aggregate active balance', function () {
    $user = User::factory()->create();
    archAccount($user, 'Checking', '100.00', false);
    archAccount($user, 'Old Savings', '50.00', true);

    $total = app(AccountBalanceService::class)->totalBalance($user->id);
    expect($total)->toBe('100.00');

    $history = activeAggregate($user, '2025-01-01', '2025-01-05');
    expect($history['2025-01-05'])->toBe('100.00');
});

test('expense on an archived account does not hit the active balance but is reported', function () {
    $user = User::factory()->create();
    archAccount($user, 'Checking', '100.00', false);
    $old = archAccount($user, 'Old', '50.00', true);

    app(TransactionService::class)->createExpense($old, ['amount' => '20.00', 'date' => '2025-01-04']);

    expect(app(AccountBalanceService::class)->totalBalance($user->id))->toBe('100.00');
    expect(app(AccountBalanceService::class)->computeBalance($old->fresh()))->toBe('30.00');

    $history = activeAggregate($user, '2025-01-01', '2025-01-06');
    expect($history['2025-01-04'])->toBe('100.00');

    $analytics = app(FinancialAnalyticsService::class)->summary($user->id, '2025-01-01', '2025-01-31');
    expect($analytics['expenses'])->toBe('20.00');
});

test('income on an archived account does not hit the active balance but is reported', function () {
    $user = User::factory()->create();
    archAccount($user, 'Checking', '100.00', false);
    $old = archAccount($user, 'Old', '50.00', true);

    app(TransactionService::class)->createIncome($old, ['amount' => '30.00', 'date' => '2025-01-04']);

    expect(app(AccountBalanceService::class)->totalBalance($user->id))->toBe('100.00');
    expect(app(AccountBalanceService::class)->computeBalance($old->fresh()))->toBe('80.00');

    $history = activeAggregate($user, '2025-01-01', '2025-01-06');
    expect($history['2025-01-04'])->toBe('100.00');

    $analytics = app(FinancialAnalyticsService::class)->summary($user->id, '2025-01-01', '2025-01-31');
    expect($analytics['income'])->toBe('30.00');
});

test('transfer active to archived reduces the active aggregate balance', function () {
    $user = User::factory()->create();
    $active = archAccount($user, 'Checking', '100.00', false);
    $old = archAccount($user, 'Old', '50.00', true);

    app(TransferService::class)->create($active, $old, ['amount' => '20.00', 'date' => '2025-01-04']);

    expect(app(AccountBalanceService::class)->computeBalance($active->fresh()))->toBe('80.00');
    expect(app(AccountBalanceService::class)->computeBalance($old->fresh()))->toBe('70.00');
    expect(app(AccountBalanceService::class)->totalBalance($user->id))->toBe('80.00');

    $history = activeAggregate($user, '2025-01-01', '2025-01-06');
    expect($history['2025-01-04'])->toBe('80.00');
});

test('transfer archived to active increases the active aggregate balance', function () {
    $user = User::factory()->create();
    $active = archAccount($user, 'Checking', '100.00', false);
    $old = archAccount($user, 'Old', '50.00', true);

    app(TransferService::class)->create($old, $active, ['amount' => '20.00', 'date' => '2025-01-04']);

    expect(app(AccountBalanceService::class)->computeBalance($active->fresh()))->toBe('120.00');
    expect(app(AccountBalanceService::class)->computeBalance($old->fresh()))->toBe('30.00');
    expect(app(AccountBalanceService::class)->totalBalance($user->id))->toBe('120.00');

    $history = activeAggregate($user, '2025-01-01', '2025-01-06');
    expect($history['2025-01-04'])->toBe('120.00');
});

test('transfer between two archived accounts leaves the active aggregate unchanged', function () {
    $user = User::factory()->create();
    archAccount($user, 'Checking', '100.00', false);

    $sync = function (Account $a) {
        return app(AccountBalanceService::class)->computeBalance($a->fresh());
    };

    $b = archAccount($user, 'Old A', '50.00', true);
    $c = archAccount($user, 'Old B', '80.00', true);
    $beforeA = $sync($b);
    $beforeB = $sync($c);

    app(TransferService::class)->create($b, $c, ['amount' => '15.00', 'date' => '2025-01-04']);

    expect(app(AccountBalanceService::class)->totalBalance($user->id))->toBe('100.00');
    expect($sync($b))->toBe('35.00');
    expect($sync($c))->toBe('95.00');
    // The two archived accounts shifted money between themselves only.
    expect($beforeA)->toBe('50.00')->and($beforeB)->toBe('80.00');

    $history = activeAggregate($user, '2025-01-01', '2025-01-06');
    expect($history['2025-01-04'])->toBe('100.00');
});
