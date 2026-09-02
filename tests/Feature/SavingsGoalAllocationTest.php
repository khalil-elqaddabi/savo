<?php

use App\Models\Account;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\SafeToSpendService;
use App\Services\SavingsGoalService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function allocationUser(array $opts = []): array
{
    $settings = array_merge([
        'locale' => 'en',
        'currency' => 'MAD',
        'theme' => 'light',
    ], $opts);
    $user = User::factory()->create($settings);

    $checking = Account::create([
        'user_id' => $user->id,
        'name' => 'Tijari Bank',
        'type' => 'bank',
        'starting_balance' => '1000.00',
        'balance' => '1000.00',
    ]);

    return [$user, $checking];
}

test('creating a goal through the app creates a linked dedicated savings account', function () {
    [$user, $checking] = allocationUser();

    $this->actingAs($user)->post('/goals', [
        'name' => 'Gaming PC',
        'target_amount' => '800.00',
        'deadline' => now()->addMonths(5)->toDateString(),
    ])->assertRedirect();

    $goal = SavingsGoal::where('user_id', $user->id)->first();
    expect($goal->account_id)->not->toBeNull();
    $savings = Account::find($goal->account_id);
    expect($savings->type)->toBe('savings')
        ->and((float) $savings->balance)->toBe(0.0);
});

test('contributing 20 dh updates saved, remaining, progress exactly', function () {
    [$user, $checking] = allocationUser();

    $goal = SavingsGoal::create([
        'user_id' => $user->id,
        'name' => 'Gaming PC',
        'target_amount' => '800.00',
        'current_amount' => '0.00',
        'deadline' => now()->addMonths(5)->toDateString(),
    ]);

    $this->actingAs($user)->post("/goals/{$goal->id}/contribute", [
        'amount' => '20.00',
        'account_id' => $checking->id,
    ])->assertRedirect();

    $status = app(SavingsGoalService::class)->status($goal->fresh(), CarbonImmutable::now());
    expect((float) $status['saved'])->toBe(20.0)
        ->and((float) $status['remaining'])->toBe(780.0)
        ->and($status['progress_percent'])->toBe(2.5);
});

test('a contribution creates exactly one real ledger transaction and a source and goal account move', function () {
    [$user, $checking] = allocationUser();

    $goal = SavingsGoal::create([
        'user_id' => $user->id,
        'name' => 'Gaming PC',
        'target_amount' => '800.00',
        'current_amount' => '0.00',
        'deadline' => now()->addMonths(5)->toDateString(),
    ]);

    $this->actingAs($user)->post("/goals/{$goal->id}/contribute", [
        'amount' => '20.00',
        'account_id' => $checking->id,
    ])->assertRedirect();

    $ledger = Transaction::where('user_id', $user->id)->get();
    expect($ledger)->toHaveCount(1)
        ->and($ledger->first()->type)->toBe('transfer')
        ->and((int) $ledger->first()->account_id)->toBe($checking->id)
        ->and((int) $ledger->first()->destination_account_id)->toBe($goal->fresh()->account_id)
        ->and((float) $ledger->first()->amount)->toBe(20.0);
});

test('available/safe to spend reflects the actual allocation while total wealth is unchanged', function () {
    [$user, $checking] = allocationUser();

    $goal = SavingsGoal::create([
        'user_id' => $user->id,
        'name' => 'Gaming PC',
        'target_amount' => '800.00',
        'current_amount' => '0.00',
        'deadline' => now()->addMonths(5)->toDateString(),
    ]);

    $this->actingAs($user)->post("/goals/{$goal->id}/contribute", [
        'amount' => '20.00',
        'account_id' => $checking->id,
    ])->assertRedirect();

    $safeToSpend = app(SafeToSpendService::class)->safeToSpend($user->id);
    $total = app(AccountBalanceService::class)->totalBalance($user->id);

    expect((float) $safeToSpend['planned_savings'])->toBe(20.0)
        ->and((float) $safeToSpend['safe_to_spend'])->toBe(980.0)
        // Total net worth unchanged — money moved between the user's own accounts.
        ->and((float) $total)->toBe(1000.0);
});

test('required monthly recommendation is never treated as committed money', function () {
    [$user, $checking] = allocationUser();

    $goal = SavingsGoal::create([
        'user_id' => $user->id,
        'name' => 'Gaming PC',
        'target_amount' => '800.00',
        'current_amount' => '0.00',
        'deadline' => now()->addMonths(5)->toDateString(),
    ]);

    // A tiny real allocation does NOT commit the whole 160 required monthly.
    $this->actingAs($user)->post("/goals/{$goal->id}/contribute", [
        'amount' => '1.00',
        'account_id' => $checking->id,
    ])->assertRedirect();

    $status = app(SavingsGoalService::class)->status($goal->fresh(), CarbonImmutable::now());
    $safe = app(SafeToSpendService::class)->safeToSpend($user->id);

    expect((float) $status['required_monthly'])->toBeGreaterThan(0.0)
        ->and((float) $safe['planned_savings'])->toBe(1.0)
        ->and((float) $safe['safe_to_spend'])->toBe(999.0);
});

test('multiple goals only reduce available money by their actual allocations (no double counting)', function () {
    [$user, $checking] = allocationUser();

    $goalA = SavingsGoal::create([
        'user_id' => $user->id,
        'name' => 'A',
        'target_amount' => '800.00',
        'current_amount' => '0.00',
        'deadline' => now()->addMonths(5)->toDateString(),
    ]);
    $goalB = SavingsGoal::create([
        'user_id' => $user->id,
        'name' => 'B',
        'target_amount' => '1200.00',
        'current_amount' => '0.00',
        'deadline' => now()->addMonths(5)->toDateString(),
    ]);

    $this->actingAs($user)->post("/goals/{$goalA->id}/contribute", [
        'amount' => '20.00',
        'account_id' => $checking->id,
    ])->assertRedirect();
    $this->actingAs($user)->post("/goals/{$goalB->id}/contribute", [
        'amount' => '30.00',
        'account_id' => $checking->id,
    ])->assertRedirect();

    $safe = app(SafeToSpendService::class)->safeToSpend($user->id);

    // Two separate allocations; nothing summed from recommendations.
    expect((float) $safe['planned_savings'])->toBe(50.0)
        ->and((float) $safe['safe_to_spend'])->toBe(950.0);
});

test('a completed goal behaves correctly and contributes no further allocation', function () {
    [$user, $checking] = allocationUser();

    $goal = SavingsGoal::create([
        'user_id' => $user->id,
        'name' => 'Done',
        'target_amount' => '800.00',
        'current_amount' => '0.00',
        'deadline' => now()->addMonths(5)->toDateString(),
    ]);

    $this->actingAs($user)->post("/goals/{$goal->id}/contribute", [
        'amount' => '800.00',
        'account_id' => $checking->id,
    ])->assertRedirect();

    $goal->refresh();
    expect($goal->is_completed)->toBeTrue()
        ->and($goal->achieved_at)->not->toBeNull();

    $safe = app(SafeToSpendService::class)->safeToSpend($user->id);
    // Fully allocated: safe to spend only counts the allocated money.
    expect((float) $safe['planned_savings'])->toBe(800.0);
});

test('a user cannot contribute to another user goal or from another user account', function () {
    [$userA, $checkingA] = allocationUser();
    [$userB, $checkingB] = allocationUser();

    $goalB = SavingsGoal::create([
        'user_id' => $userB->id,
        'name' => 'B',
        'target_amount' => '800.00',
        'current_amount' => '0.00',
        'deadline' => now()->addMonths(5)->toDateString(),
    ]);

    // A cannot contribute to B's goal.
    $this->actingAs($userA)
        ->post("/goals/{$goalB->id}/contribute", [
            'amount' => '20.00',
            'account_id' => $checkingA->id,
        ])
        ->assertForbidden();

    // A cannot use B's account as the source for their own goal. The source is
    // scoped to the goal's owner, so another user's account is not found (404).
    $goalA = SavingsGoal::create([
        'user_id' => $userA->id,
        'name' => 'A',
        'target_amount' => '800.00',
        'current_amount' => '0.00',
        'deadline' => now()->addMonths(5)->toDateString(),
    ]);
    $this->actingAs($userA)
        ->post("/goals/{$goalA->id}/contribute", [
            'amount' => '20.00',
            'account_id' => $checkingB->id,
        ])
        ->assertNotFound();

    expect(SavingsGoal::find($goalB->id)->current_amount)->toBe('0.00')
        ->and(SavingsGoal::find($goalA->id)->current_amount)->toBe('0.00');
});

test('decimal contributions allocate exact cents without double counting', function () {
    [$user, $checking] = allocationUser();

    $goal = SavingsGoal::create([
        'user_id' => $user->id,
        'name' => 'Gaming PC',
        'target_amount' => '800.00',
        'current_amount' => '0.00',
        'deadline' => now()->addMonths(5)->toDateString(),
    ]);

    $this->actingAs($user)->post("/goals/{$goal->id}/contribute", [
        'amount' => '10.45',
        'account_id' => $checking->id,
    ])->assertRedirect();

    $status = app(SavingsGoalService::class)->status($goal->fresh(), CarbonImmutable::now());
    $safe = app(SafeToSpendService::class)->safeToSpend($user->id);

    expect((float) $status['saved'])->toBe(10.45)
        ->and((float) $safe['planned_savings'])->toBe(10.45)
        ->and((float) $safe['safe_to_spend'])->toBe(989.55);
});
