<?php

use App\Models\Account;
use App\Models\Bill;
use App\Models\Debt;
use App\Models\User;
use App\Services\BillService;
use App\Services\DebtService;
use App\Services\ForecastService;
use App\Services\HealthScoreService;
use App\Services\SafeToSpendService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function phase1User(array $opts = []): array
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

test('an active monthly bill is folded into safe to spend obligations', function () {
    [$user, $checking] = phase1User();

    $reference = CarbonImmutable::now()->startOfMonth()->addDays(5);

    Bill::create([
        'user_id' => $user->id,
        'name' => 'Netflix',
        'amount' => '50.00',
        'currency' => 'MAD',
        'frequency' => Bill::FREQ_MONTHLY,
        'interval' => 1,
        'next_payment_date' => $reference->toDateString(),
        'status' => Bill::STATUS_ACTIVE,
    ]);

    $result = app(SafeToSpendService::class)->safeToSpend($user->id, $reference);

    expect((float) $result['upcoming_obligations'])->toBe(50.0)
        ->and((float) $result['safe_to_spend'])->toBe(950.0);
});

test('a monthly debt installment is folded into safe to spend obligations', function () {
    [$user, $checking] = phase1User();

    $reference = CarbonImmutable::now()->startOfMonth()->addDays(5);

    Debt::create([
        'user_id' => $user->id,
        'name' => 'Car Loan',
        'type' => Debt::TYPE_LOAN,
        'original_amount' => '5000.00',
        'remaining_amount' => '3000.00',
        'interest_rate' => '0',
        'installment_amount' => '200.00',
        'frequency' => Debt::FREQ_MONTHLY,
        'next_payment_date' => $reference->toDateString(),
        'status' => Debt::STATUS_ACTIVE,
    ]);

    $result = app(SafeToSpendService::class)->safeToSpend($user->id, $reference);

    expect((float) $result['upcoming_obligations'])->toBe(200.0)
        ->and((float) $result['safe_to_spend'])->toBe(800.0);
});

test('bill and debt monthly cost appear in the monthly forecast expenses', function () {
    [$user, $checking] = phase1User();

    $reference = CarbonImmutable::now();

    Bill::create([
        'user_id' => $user->id,
        'name' => 'Netflix',
        'amount' => '50.00',
        'currency' => 'MAD',
        'frequency' => Bill::FREQ_MONTHLY,
        'interval' => 1,
        'next_payment_date' => $reference->toDateString(),
        'status' => Bill::STATUS_ACTIVE,
    ]);

    Debt::create([
        'user_id' => $user->id,
        'name' => 'Car Loan',
        'type' => Debt::TYPE_LOAN,
        'original_amount' => '5000.00',
        'remaining_amount' => '3000.00',
        'interest_rate' => '0',
        'installment_amount' => '200.00',
        'frequency' => Debt::FREQ_MONTHLY,
        'next_payment_date' => $reference->toDateString(),
        'status' => Debt::STATUS_ACTIVE,
    ]);

    $forecast = app(ForecastService::class)->forecastForMonth($user->id, $reference);

    expect((float) $forecast['expected_expenses'])->toBe(250.0);
});

test('held-to-user debts never reduce safe to spend', function () {
    [$user, $checking] = phase1User();

    $reference = CarbonImmutable::now();

    Debt::create([
        'user_id' => $user->id,
        'name' => 'Owed to me',
        'type' => Debt::TYPE_OWED_TO_USER,
        'original_amount' => '500.00',
        'remaining_amount' => '500.00',
        'interest_rate' => '0',
        'installment_amount' => '0',
        'frequency' => Debt::FREQ_MONTHLY,
        'status' => Debt::STATUS_ACTIVE,
    ]);

    $result = app(SafeToSpendService::class)->safeToSpend($user->id, $reference);

    expect((float) $result['upcoming_obligations'])->toBe(0.0)
        ->and((float) $result['safe_to_spend'])->toBe(1000.0);
});

test('bill service monthly cost aggregates multiple active bills', function () {
    [$user, $checking] = phase1User();

    $n = now()->toDateString();
    Bill::create(['user_id' => $user->id, 'name' => 'A', 'amount' => '10.00', 'currency' => 'MAD', 'frequency' => Bill::FREQ_MONTHLY, 'interval' => 1, 'next_payment_date' => $n, 'status' => Bill::STATUS_ACTIVE]);
    Bill::create(['user_id' => $user->id, 'name' => 'B', 'amount' => '20.00', 'currency' => 'MAD', 'frequency' => Bill::FREQ_WEEKLY, 'interval' => 1, 'next_payment_date' => $n, 'status' => Bill::STATUS_ACTIVE]);
    Bill::create(['user_id' => $user->id, 'name' => 'C', 'amount' => '50.00', 'currency' => 'MAD', 'frequency' => Bill::FREQ_YEARLY, 'interval' => 1, 'next_payment_date' => $n, 'status' => Bill::STATUS_ACTIVE]);
    Bill::create(['user_id' => $user->id, 'name' => 'D', 'amount' => '100.00', 'currency' => 'MAD', 'frequency' => Bill::FREQ_MONTHLY, 'interval' => 1, 'next_payment_date' => $n, 'status' => Bill::STATUS_CANCELLED]);

    $monthly = app(BillService::class)->monthlyCost($user->id);

    // Monthly 10.00 + weekly (20*52/12 rounded up) 86.67 + yearly (50/12 rounded up) 4.17.
    // Cancelled excluded. Individual bills are rounded to the cent (HALF_UP).
    expect((float) $monthly)->toBe(100.84);
});

test('debt service summaries only count debts the user owes', function () {
    [$user, $checking] = phase1User();

    Debt::create(['user_id' => $user->id, 'name' => 'Loan', 'type' => Debt::TYPE_LOAN, 'original_amount' => '1000.00', 'remaining_amount' => '400.00', 'interest_rate' => '0', 'installment_amount' => '100.00', 'frequency' => Debt::FREQ_MONTHLY, 'status' => Debt::STATUS_ACTIVE]);
    Debt::create(['user_id' => $user->id, 'name' => 'Owed to me', 'type' => Debt::TYPE_OWED_TO_USER, 'original_amount' => '500.00', 'remaining_amount' => '500.00', 'interest_rate' => '0', 'installment_amount' => '0', 'frequency' => Debt::FREQ_MONTHLY, 'status' => Debt::STATUS_ACTIVE]);

    $summary = app(DebtService::class)->summary($user->id);

    expect((float) $summary['total_remaining'])->toBe(400.0)
        ->and((float) $summary['monthly_payments'])->toBe(100.0)
        ->and($summary['count'])->toBe(1);
});

test('health score is deterministic and within 0-100', function () {
    [$user, $checking] = phase1User();

    $score = app(HealthScoreService::class)->score($user->id);

    expect($score['score'])->toBeInt()
        ->and($score['score'])->toBeGreaterThanOrEqual(0)
        ->and($score['score'])->toBeLessThanOrEqual(100)
        ->and($score['factors'])->toHaveKey('savings_rate')
        ->and($score['factors'])->toHaveKey('debt_load')
        ->and($score['generated_at'])->not->toBeNull();
});

test('bills and debts are strictly user-scoped across unrelated users', function () {
    [$userA, $checkingA] = phase1User();
    [$userB, $checkingB] = phase1User();

    Bill::create(['user_id' => $userA->id, 'name' => 'A Bill', 'amount' => '30.00', 'currency' => 'MAD', 'frequency' => Bill::FREQ_MONTHLY, 'interval' => 1, 'next_payment_date' => now()->toDateString(), 'status' => Bill::STATUS_ACTIVE]);

    expect((float) app(BillService::class)->monthlyCost($userB->id))->toBe(0.0);

    $result = app(SafeToSpendService::class)->safeToSpend($userB->id);
    expect((float) $result['upcoming_obligations'])->toBe(0.0);
});
