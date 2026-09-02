<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\TransactionService;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeUserWithAccounts(): array
{
    $user = User::factory()->create();

    $checking = Account::create([
        'user_id' => $user->id,
        'name' => 'Checking',
        'type' => 'bank',
        'starting_balance' => '100.00',
        'balance' => '100.00',
    ]);

    $savings = Account::create([
        'user_id' => $user->id,
        'name' => 'Savings',
        'type' => 'savings',
        'starting_balance' => '50.00',
        'balance' => '50.00',
    ]);

    return [$user, $checking, $savings];
}

test('income increases account balance', function () {
    [$user, $checking, $savings] = makeUserWithAccounts();

    $tx = app(TransactionService::class)->createIncome($checking, [
        'amount' => '250.50',
        'description' => 'Salary',
        'date' => now()->toDateString(),
    ]);

    expect($tx->type)->toBe('income')
        ->and($checking->fresh()->balance)->toBe('350.50');
});

test('expense decreases account balance', function () {
    [$user, $checking, $savings] = makeUserWithAccounts();

    app(TransactionService::class)->createExpense($checking, [
        'amount' => '40.25',
        'description' => 'Groceries',
        'date' => now()->toDateString(),
    ]);

    expect($checking->fresh()->balance)->toBe('59.75');
});

test('transfer moves money without net balance change and is not income', function () {
    [$user, $checking, $savings] = makeUserWithAccounts();

    app(TransferService::class)->create($checking, $savings, [
        'amount' => '30.00',
        'date' => now()->toDateString(),
    ]);

    expect($checking->fresh()->balance)->toBe('70.00')
        ->and($savings->fresh()->balance)->toBe('80.00')
        ->and(app(AccountBalanceService::class)->totalBalance($user->id))->toBe('150.00')
        ->and(Transaction::where('type', 'income')->count())->toBe(0)
        ->and(Transaction::where('type', 'expense')->count())->toBe(0);
});

test('transfer to the same account is rejected', function () {
    [$user, $checking, $savings] = makeUserWithAccounts();

    expect(fn () => app(TransferService::class)->create($checking, $checking, [
        'amount' => '10.00',
    ]))->toThrow(InvalidArgumentException::class);
});

test('computing balance matches ledger', function () {
    [$user, $checking, $savings] = makeUserWithAccounts();

    $ts = app(TransactionService::class);
    $ts->createIncome($checking, ['amount' => '500.00']);
    $ts->createExpense($checking, ['amount' => '120.50']);
    app(TransferService::class)->create($checking, $savings, ['amount' => '40.00']);

    // 100.00 + 500.00 - 120.50 - 40.00 = 439.50
    expect(app(AccountBalanceService::class)->computeBalance($checking->fresh()))->toBe('439.50');
});

test('user data is isolated from other users', function () {
    [$userA, $aCheck, $aSave] = makeUserWithAccounts();
    [$userB] = makeUserWithAccounts();

    $ts = app(TransactionService::class);
    $ts->createIncome($aCheck, ['amount' => '300.00']);

    expect(Transaction::where('user_id', $userA->id)->count())->toBe(1)
        ->and(Transaction::where('user_id', $userB->id)->count())->toBe(0);
});

test('total balance across accounts sums correctly', function () {
    [$user, $checking, $savings] = makeUserWithAccounts();

    $ts = app(TransactionService::class);
    $ts->createExpense($checking, ['amount' => '10.00']);

    expect(app(AccountBalanceService::class)->totalBalance($user->id))->toBe('140.00');
});

test('category type must match transaction type', function () {
    $user = User::factory()->create();
    $incomeCat = Category::create(['user_id' => null, 'name' => 'Salary', 'type' => 'income']);
    $expenseCat = Category::create(['user_id' => null, 'name' => 'Rent', 'type' => 'expense']);

    expect(TransactionService::validateCategoryType($incomeCat, 'income'))->toBeTrue()
        ->and(TransactionService::validateCategoryType($expenseCat, 'expense'))->toBeTrue()
        ->and(TransactionService::validateCategoryType($incomeCat, 'expense'))->toBeFalse()
        ->and(TransactionService::validateCategoryType(null, 'income'))->toBeTrue();
});

test('the exact submitted transaction date is persisted for every type', function () {
    [$user, $checking, $savings] = makeUserWithAccounts();

    $cases = [
        ['type' => 'income', 'account_id' => $checking->id, 'amount' => '100.00', 'date' => '2026-08-20'],
        ['type' => 'expense', 'account_id' => $checking->id, 'amount' => '50.00', 'date' => '2026-08-15'],
        ['type' => 'transfer', 'account_id' => $checking->id, 'destination_account_id' => $savings->id, 'amount' => '30.00', 'date' => '2026-08-10'],
    ];

    foreach ($cases as $c) {
        $this->actingAs($user)->post('/transactions', $c)->assertRedirect();

        $tx = Transaction::query()
            ->where('user_id', $user->id)
            ->where('date', $c['date'])
            ->first();

        expect($tx)->not->toBeNull()
            ->and($tx->type)->toBe($c['type'])
            ->and($tx->date->toDateString())->toBe($c['date'])
            ->and($tx->getRawOriginal('date'))->toBe($c['date']);
    }

    // Exactly the three transactions were created; none leaked into another day.
    expect(Transaction::where('user_id', $user->id)->count())->toBe(3);
});
