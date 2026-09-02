<?php

use App\Models\Account;
use App\Models\RecurringTransaction;
use App\Models\User;
use App\Services\RecurringTransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function recurring(User $user, array $overrides = []): RecurringTransaction
{
    $account = Account::create([
        'user_id' => $user->id,
        'name' => 'Checking',
        'type' => 'bank',
        'starting_balance' => '0.00',
        'balance' => '0.00',
    ]);

    return RecurringTransaction::create(array_merge([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'name' => 'Bill',
        'type' => 'expense',
        'amount' => '100.00',
        'frequency' => 'monthly',
        'interval' => 1,
        'start_date' => '2025-01-15',
        'next_occurrence' => '2025-01-15',
        'is_active' => true,
    ], $overrides));
}

test('monthly occurrences preserve the configured day of month', function () {
    $user = User::factory()->create();
    $r = recurring($user, ['next_occurrence' => '2025-01-15', 'start_date' => '2025-01-15']);

    // Query upcoming occurrences in February 2025 — should still be the 15th.
    $dates = app(RecurringTransactionService::class)
        ->occurrencesIn($r, CarbonImmutable::parse('2025-02-01'), CarbonImmutable::parse('2025-02-28'))
        ->map(fn ($d) => $d->toDateString())
        ->all();

    expect($dates)->toBe(['2025-02-15']);
});

test('weekly occurrences preserve the configured weekday', function () {
    $user = User::factory()->create();
    // 2025-01-15 is a Wednesday.
    $r = recurring($user, [
        'frequency' => 'weekly',
        'next_occurrence' => '2025-01-15',
        'start_date' => '2025-01-15',
    ]);

    $dates = app(RecurringTransactionService::class)
        ->occurrencesIn($r, CarbonImmutable::parse('2025-02-01'), CarbonImmutable::parse('2025-02-14'))
        ->map(fn ($d) => $d->toDateString())
        ->all();

    // Wednesdays in that window are 2025-02-05 and 2025-02-12.
    expect($dates)->toBe(['2025-02-05', '2025-02-12']);
});

test('daily occurrences land on every day in the window', function () {
    $user = User::factory()->create();
    $r = recurring($user, [
        'frequency' => 'daily',
        'next_occurrence' => '2025-01-15',
        'start_date' => '2025-01-15',
    ]);

    $dates = app(RecurringTransactionService::class)
        ->occurrencesIn($r, CarbonImmutable::parse('2025-01-16'), CarbonImmutable::parse('2025-01-18'))
        ->map(fn ($d) => $d->toDateString())
        ->all();

    expect($dates)->toBe(['2025-01-16', '2025-01-17', '2025-01-18']);
});

test('recurring bounded by end date stops generating', function () {
    $user = User::factory()->create();
    $r = recurring($user, [
        'next_occurrence' => '2025-01-15',
        'start_date' => '2025-01-15',
        'end_date' => '2025-03-14',
    ]);

    $dates = app(RecurringTransactionService::class)
        ->occurrencesIn($r, CarbonImmutable::parse('2025-01-01'), CarbonImmutable::parse('2025-06-30'))
        ->map(fn ($d) => $d->toDateString())
        ->all();

    // 15th of Jan and Feb; Mar 15 exceeds end_date (2025-03-14) so it is excluded.
    expect($dates)->toBe(['2025-01-15', '2025-02-15']);
});
