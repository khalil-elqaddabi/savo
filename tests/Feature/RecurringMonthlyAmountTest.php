<?php

use App\Models\RecurringTransaction;
use App\Models\User;
use App\Services\RecurringTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('daily recurring contributes 30x the per-occurrence amount monthly', function () {
    $user = User::factory()->create();
    $rr = recurring($user, ['frequency' => 'daily', 'amount' => '100.00', 'interval' => 1]);

    expect(app(RecurringTransactionService::class)->monthlyAmount($rr))->toBe('3000.00');
});

test('daily recurring monthly amount scales with the interval', function () {
    $user = User::factory()->create();
    $rr = recurring($user, ['frequency' => 'daily', 'amount' => '10.00', 'interval' => 2]);

    expect(app(RecurringTransactionService::class)->monthlyAmount($rr))->toBe('600.00');
});

test('weekly recurring contributes 52/12 of the per-occurrence amount monthly', function () {
    $user = User::factory()->create();
    $rr = recurring($user, ['frequency' => 'weekly', 'amount' => '120.00', 'interval' => 1]);

    expect(app(RecurringTransactionService::class)->monthlyAmount($rr))->toBe('520.00');
});

test('monthly recurring contributes amount times interval', function () {
    $user = User::factory()->create();
    $rr = recurring($user, ['frequency' => 'monthly', 'amount' => '100.00', 'interval' => 3]);

    expect(app(RecurringTransactionService::class)->monthlyAmount($rr))->toBe('300.00');
});

test('yearly recurring contributes one twelfth of the amount monthly', function () {
    $user = User::factory()->create();
    $rr = recurring($user, ['frequency' => 'yearly', 'amount' => '1200.00', 'interval' => 1]);

    expect(app(RecurringTransactionService::class)->monthlyAmount($rr))->toBe('100.00');
});

test('daily recurring with decimal amount keeps cent precision', function () {
    $user = User::factory()->create();
    $rr = recurring($user, ['frequency' => 'daily', 'amount' => '10.33', 'interval' => 1]);

    expect(app(RecurringTransactionService::class)->monthlyAmount($rr))->toBe('309.90');
});
