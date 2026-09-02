<?php

use App\Models\Account;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Services\SafeToSpendService;
use App\Services\SavingsGoalService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A goal's required monthly contribution is a recommendation, not money that
 * has left the available balance. Only genuinely committed money may reduce
 * Safe to Spend. The current model has no mechanism that moves goal money out
 * of the account balances, so goals must not reduce Safe to Spend at all.
 */
function safeSpendUser(array $opts = []): array
{
    $settings = array_merge([
        'locale' => 'en',
        'currency' => 'MAD',
        'theme' => 'light',
    ], $opts);
    $user = User::factory()->create($settings);

    $checking = Account::create([
        'user_id' => $user->id,
        'name' => 'Checking',
        'type' => 'bank',
        'starting_balance' => '1000.00',
        'balance' => '1000.00',
    ]);

    return [$user, $checking];
}

test('creating a goal with zero saved does not reduce safe to spend', function () {
    [$user, $checking] = safeSpendUser();

    $before = app(SafeToSpendService::class)->safeToSpend($user->id);

    SavingsGoal::create([
        'user_id' => $user->id,
        'name' => 'sns',
        'target_amount' => '800.00',
        'current_amount' => '0.00',
        'deadline' => now()->addMonths(5)->toDateString(),
    ]);

    $after = app(SafeToSpendService::class)->safeToSpend($user->id);

    // Without any recurring obligations or protected money the whole balance is spendable.
    expect((float) $after['safe_to_spend'])->toBe(1000.0)
        ->and((float) $after['safe_to_spend'])->toBe((float) $before['safe_to_spend'])
        ->and((float) $after['planned_savings'])->toBe(0.0);
});

test('required monthly contribution is displayed but never subtracted as committed money', function () {
    [$user, $checking] = safeSpendUser();

    $goal = SavingsGoal::create([
        'user_id' => $user->id,
        'name' => 'sns',
        'target_amount' => '800.00',
        'current_amount' => '0.00',
        'deadline' => now()->addMonths(5)->toDateString(),
    ]);

    $status = app(SavingsGoalService::class)->status($goal, CarbonImmutable::now());
    $safe = app(SafeToSpendService::class)->safeToSpend($user->id);

    expect((float) $status['required_monthly'])->toBeGreaterThan(0.0)
        ->and((float) $safe['planned_savings'])->toBe(0.0)
        ->and((float) $safe['safe_to_spend'])->toBe(1000.0);
});

test('adding a contribution allocates real money: saved, remaining and progress update and safe to spend falls by the allocation', function () {
    [$user, $checking] = safeSpendUser();

    $goal = SavingsGoal::create([
        'user_id' => $user->id,
        'name' => 'sns',
        'target_amount' => '800.00',
        'current_amount' => '0.00',
        'deadline' => now()->addMonths(5)->toDateString(),
    ]);

    $this->actingAs($user)->post('/goals/' . $goal->id . '/contribute', [
        'amount' => '20.00',
        'account_id' => $checking->id,
    ])->assertRedirect();

    $status = app(SavingsGoalService::class)->status($goal->fresh(), CarbonImmutable::now());
    $safe = app(SafeToSpendService::class)->safeToSpend($user->id);

    expect((float) $status['saved'])->toBe(20.0)
        ->and((float) $status['remaining'])->toBe(780.0)
        ->and($status['progress_percent'])->toBe(2.5)
        // The 20 DH is now a real allocation: the source account lost it and it
        // is non-spendable, so Safe to Spend drops by exactly the allocation.
        ->and((float) $safe['safe_to_spend'])->toBe(980.0)
        ->and((float) $safe['planned_savings'])->toBe(20.0);
});

test('a contribution is a transfer into the goal savings account: source drops, total wealth unchanged, exactly one ledger entry', function () {
    [$user, $checking] = safeSpendUser();

    $goal = SavingsGoal::create([
        'user_id' => $user->id,
        'name' => 'Gaming PC',
        'target_amount' => '800.00',
        'current_amount' => '0.00',
        'deadline' => now()->addMonths(5)->toDateString(),
    ]);

    $this->actingAs($user)->post('/goals/' . $goal->id . '/contribute', [
        'amount' => '20.00',
        'account_id' => $checking->id,
    ])->assertRedirect();

    $goal->refresh();
    $savings = $goal->account;

    expect($savings)->not->toBeNull()
        ->and($savings->type)->toBe('savings')
        ->and((float) $savings->balance)->toBe(20.0)
        ->and((float) $checking->fresh()->balance)->toBe(980.0);

    // Total wealth across all accounts is unchanged (a transfer, not an expense).
    $total = app(\App\Services\AccountBalanceService::class)->totalBalance($user->id);
    expect((float) $total)->toBe(1000.0);

    // Exactly one (transfer) ledger entry was created for the contribution.
    expect(\App\Models\Transaction::query()->where('user_id', $user->id)->where('type', 'transfer')->count())->toBe(1);
});

test('multiple goals are never summed into planned savings', function () {
    [$user, $checking] = safeSpendUser();

    foreach ([
        ['name' => 'A', 'target_amount' => '800.00', 'current_amount' => '0.00'],
        ['name' => 'B', 'target_amount' => '1200.00', 'current_amount' => '0.00'],
    ] as $g) {
        SavingsGoal::create(array_merge($g, [
            'user_id' => $user->id,
            'deadline' => now()->addMonths(5)->toDateString(),
        ]));
    }

    $safe = app(SafeToSpendService::class)->safeToSpend($user->id);

    expect((float) $safe['planned_savings'])->toBe(0.0)
        ->and((float) $safe['safe_to_spend'])->toBe(1000.0);
});

test('a completed goal never contributes a required future contribution', function () {
    [$user, $checking] = safeSpendUser();

    SavingsGoal::create([
        'user_id' => $user->id,
        'name' => 'Done',
        'target_amount' => '800.00',
        'current_amount' => '800.00',
        'deadline' => now()->addMonths(5)->toDateString(),
        'is_completed' => true,
    ]);

    $goal = SavingsGoal::create([
        'user_id' => $user->id,
        'name' => 'Open',
        'target_amount' => '800.00',
        'current_amount' => '0.00',
        'deadline' => now()->addMonths(5)->toDateString(),
    ]);

    $status = app(SavingsGoalService::class)->status($goal, CarbonImmutable::now());
    $safe = app(SafeToSpendService::class)->safeToSpend($user->id);

    expect((float) $safe['planned_savings'])->toBe(0.0)
        ->and((float) $safe['safe_to_spend'])->toBe(1000.0);
});

test('past deadline goals report no required contributions and zero days remaining', function () {
    [$user, $checking] = safeSpendUser();

    $goal = SavingsGoal::create([
        'user_id' => $user->id,
        'name' => 'Overdue',
        'target_amount' => '800.00',
        'current_amount' => '0.00',
        'deadline' => now()->subMonths(1)->toDateString(),
    ]);

    $status = app(SavingsGoalService::class)->status($goal, CarbonImmutable::now());

    expect($status['required_daily'])->toBeNull()
        ->and($status['required_weekly'])->toBeNull()
        ->and($status['required_monthly'])->toBeNull()
        ->and($status['days_remaining'])->toBe(0);
});
