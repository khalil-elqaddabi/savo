<?php

use App\Models\Account;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RecurringTransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a static next_occurrence in the past still yields the correct next future occurrence', function () {
    $user = User::factory()->create();
    // Anchored on the 15th, but the anchor date (2025-01-15) is already past.
    $rr = recurring($user, ['next_occurrence' => '2025-01-15', 'start_date' => '2025-01-15']);

    $now = CarbonImmutable::parse('2025-02-05');
    $dates = app(RecurringTransactionService::class)
        ->occurrencesIn($rr, $now, $now->addDays(30))
        ->map(fn ($d) => $d->toDateString())
        ->all();

    // The very next future occurrence must still honour the 15th, not drift to now.
    expect($dates[0])->toBe('2025-02-15');
});

test('recurring bounded by end_date does not generate occurrences after it', function () {
    $user = User::factory()->create();
    $rr = recurring($user, [
        'next_occurrence' => '2025-01-15',
        'start_date' => '2025-01-15',
        'end_date' => '2025-02-14',
    ]);

    $now = CarbonImmutable::parse('2025-01-20');
    $dates = app(RecurringTransactionService::class)
        ->occurrencesIn($rr, $now, $now->addMonthsNoOverflow(3))
        ->map(fn ($d) => $d->toDateString())
        ->all();

    // Feb 15th exceeds end_date (2025-02-14) so only Jan 20.. in-range occurrence is Jan 15 (already past) -> none in Jan window.
    // From Jan 20 onward the only candidate would be Feb 15, which is cut off.
    expect($dates)->toBe([]);
});

test('monthly summary aggregates income and expense occurrences for a month', function () {
    $user = User::factory()->create();
    recurring($user, [
        'name' => 'Salary',
        'type' => 'income',
        'amount' => '500.00',
        'frequency' => 'monthly',
        'next_occurrence' => '2025-02-15',
        'start_date' => '2025-02-15',
    ]);
    recurring($user, [
        'name' => 'Rent',
        'type' => 'expense',
        'amount' => '100.00',
        'frequency' => 'weekly',
        'next_occurrence' => '2025-02-03',
        'start_date' => '2025-02-03',
    ]);

    $month = CarbonImmutable::parse('2025-02-10');
    $summary = app(RecurringTransactionService::class)
        ->monthlySummaryForUser($user->id, $month);

    // Income: 500 on 2025-02-15. Expense: weekly on 02-03, 02-10, 02-17, 02-24 = 400.
    expect($summary['income'])->toBe('500.00')
        ->and($summary['expense'])->toBe('400.00');
});

test('inactive recurring transactions contribute nothing to the monthly summary', function () {
    $user = User::factory()->create();
    recurring($user, [
        'type' => 'expense',
        'amount' => '50.00',
        'frequency' => 'monthly',
        'next_occurrence' => '2025-02-15',
        'start_date' => '2025-02-15',
        'is_active' => false,
    ]);

    $summary = app(RecurringTransactionService::class)
        ->monthlySummaryForUser($user->id, CarbonImmutable::parse('2025-02-10'));

    expect($summary['expense'])->toBe('0.00');
});

test('a recurring transaction is created via the store endpoint and defaults next occurrence to start date', function () {
    $user = User::factory()->create();
    $account = Account::create([
        'user_id' => $user->id,
        'name' => 'Main Checking',
        'type' => 'bank',
        'starting_balance' => '0.00',
        'balance' => '0.00',
    ]);

    $this->actingAs($user)->post('/recurring', [
        'name' => 'Monthly Gym',
        'type' => 'expense',
        'amount' => '150.00',
        'frequency' => 'monthly',
        'start_date' => '2026-08-31',
        'account_id' => $account->id,
    ])->assertRedirect();

    $rr = RecurringTransaction::where('user_id', $user->id)->first();

    expect($rr)->not->toBeNull()
        ->and($rr->account_id)->toBe($account->id)
        ->and($rr->amount)->toBe('150.00')
        ->and($rr->frequency)->toBe('monthly')
        ->and($rr->start_date->toDateString())->toBe('2026-08-31')
        ->and($rr->next_occurrence->toDateString())->toBe('2026-08-31');
});

test('an invalid recurring submission returns validation errors for required fields', function () {
    $user = User::factory()->create();

    $resp = $this->actingAs($user)->post('/recurring', [
        'name' => 'Bad Recurring',
        'type' => 'expense',
        'amount' => '30.00',
        'frequency' => 'monthly',
    ], ['Accept' => 'application/json', 'X-Inertia' => 'true']);

    $resp->assertStatus(422);
    $resp->assertJsonValidationErrors(['account_id', 'start_date']);

    expect(RecurringTransaction::where('user_id', $user->id)->count())->toBe(0);
});
