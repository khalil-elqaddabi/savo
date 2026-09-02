<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\BudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function financeUser(): array
{
    $user = User::factory()->create();
    // Mirror AccountController::store() which sets starting_balance = balance.
    $account = Account::create([
        'user_id' => $user->id,
        'name' => 'Tijari Bank',
        'type' => 'bank',
        'starting_balance' => '1000.00',
        'balance' => '1000.00',
    ]);

    return [$user, $account];
}

function systemExpenseCategory(): Category
{
    return Category::find(Category::where('type', 'expense')->where('is_system', true)->first()->id);
}

/*
|--------------------------------------------------------------------------
| PROBLEM 2 — Account "Add money" (income transaction on an existing account)
|--------------------------------------------------------------------------
*/

test('adding money to an existing account raises its balance to 1500 without creating another account', function () {
    [$user, $account] = financeUser();
    $totalBefore = app(AccountBalanceService::class)->totalBalance($user->id);
    expect($totalBefore)->toBe('1000.00');

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'income',
        'account_id' => $account->id,
        'amount' => '500.00',
        'date' => now()->toDateString(),
    ])->assertRedirect();

    expect((float) app(AccountBalanceService::class)->computeBalance($account->fresh()))->toBe(1500.0)
        ->and((float) app(AccountBalanceService::class)->totalBalance($user->id))->toBe(1500.0)
        // Exactly one transaction and still one account.
        ->and(Transaction::where('user_id', $user->id)->count())->toBe(1)
        ->and($user->accounts()->count())->toBe(1)
        // starting_balance must NOT change when adding money after creation.
        ->and((float) $account->fresh()->starting_balance)->toBe(1000.0);
});

test('adding money is a real income transaction, not a starting balance mutation', function () {
    [$user, $account] = financeUser();

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'income',
        'account_id' => $account->id,
        'amount' => '500.00',
        'date' => now()->toDateString(),
    ])->assertRedirect();

    $tx = Transaction::where('user_id', $user->id)->first();
    expect($tx->type)->toBe('income')
        ->and((int) $tx->account_id)->toBe($account->id)
        ->and((float) $tx->amount)->toBe(500.0);
});

test('adding money supports decimal amounts exactly', function () {
    [$user, $account] = financeUser();

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'income',
        'account_id' => $account->id,
        'amount' => '123.45',
        'date' => now()->toDateString(),
    ])->assertRedirect();

    expect((float) app(AccountBalanceService::class)->computeBalance($account->fresh()))->toBe(1123.45);
});

test('a user cannot add money to another user account', function () {
    [$userA, $accountA] = financeUser();
    $userB = User::factory()->create();

    $this->actingAs($userB)->post(route('transactions.store'), [
        'type' => 'income',
        'account_id' => $accountA->id,
        'amount' => '500.00',
        'date' => now()->toDateString(),
    ])->assertNotFound();

    expect((float) app(AccountBalanceService::class)->computeBalance($accountA->fresh()))->toBe(1000.0);
});

/*
|--------------------------------------------------------------------------
| PROBLEM 3 — Budget "Spend" (real expense transaction feeds budget spent)
|--------------------------------------------------------------------------
*/

test('creating a budget does not change the account balance and starts with zero spent', function () {
    [$user, $account] = financeUser();

    $this->actingAs($user)->post(route('budgets.store'), [
        'name' => 'Groceries',
        'scope' => 'category',
        'category_id' => systemExpenseCategory()->id,
        'period' => 'monthly',
        'amount' => '500.00',
    ])->assertRedirect();

    $budget = $user->budgets()->first();
    $status = app(BudgetService::class)->status($budget);

    expect((float) app(AccountBalanceService::class)->totalBalance($user->id))->toBe(1000.0)
        ->and($status['spent'])->toBe('0.00')
        ->and($status['remaining'])->toBe('500.00');
});

test('spending 100 on a budget creates one expense, drops the balance by 100 and sets budget spent to 100', function () {
    [$user, $account] = financeUser();
    $category = systemExpenseCategory();
    $budget = $user->budgets()->create([
        'name' => 'Groceries',
        'scope' => 'category',
        'category_id' => $category->id,
        'period' => 'monthly',
        'amount' => '500.00',
    ]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'expense',
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => '100.00',
        'date' => now()->toDateString(),
    ])->assertRedirect();

    $status = app(BudgetService::class)->status($budget);

    expect(Transaction::where('user_id', $user->id)->count())->toBe(1)
        ->and((float) app(AccountBalanceService::class)->computeBalance($account->fresh()))->toBe(900.0)
        ->and((float) app(AccountBalanceService::class)->totalBalance($user->id))->toBe(900.0)
        ->and($status['spent'])->toBe('100.00')
        ->and($status['remaining'])->toBe('400.00');
});

test('income and transfers do not count as budget spending', function () {
    [$user, $account] = financeUser();
    $category = systemExpenseCategory();
    $other = Account::create(['user_id' => $user->id, 'name' => 'Cash', 'type' => 'cash', 'starting_balance' => '0.00', 'balance' => '0.00']);
    $budget = $user->budgets()->create([
        'name' => 'Overall',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '500.00',
    ]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'income', 'account_id' => $account->id, 'amount' => '200.00', 'date' => now()->toDateString(),
    ]);
    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'transfer', 'account_id' => $account->id, 'destination_account_id' => $other->id, 'amount' => '100.00', 'date' => now()->toDateString(),
    ]);

    $status = app(BudgetService::class)->status($budget);
    expect($status['spent'])->toBe('0.00');
});

test('a monthly budget only counts expenses in its month', function () {
    [$user, $account] = financeUser();
    $budget = $user->budgets()->create([
        'name' => 'Monthly',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '1000.00',
    ]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'expense', 'account_id' => $account->id, 'amount' => '100.00',
        'date' => now()->subMonth()->toDateString(),
    ]);
    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'expense', 'account_id' => $account->id, 'amount' => '250.00',
        'date' => now()->toDateString(),
    ]);

    $status = app(BudgetService::class)->status($budget);
    expect($status['spent'])->toBe('250.00');
});

test('a category budget only counts matching category expenses', function () {
    [$user, $account] = financeUser();
    $food = Category::where('type', 'expense')->where('is_system', true)->first();
    $rent = Category::where('type', 'expense')->where('is_active', true)->where('id', '!=', $food->id)->first();

    $budget = $user->budgets()->create([
        'name' => 'Food',
        'scope' => 'category',
        'category_id' => $food->id,
        'period' => 'monthly',
        'amount' => '300.00',
    ]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'expense', 'account_id' => $account->id, 'category_id' => $food->id, 'amount' => '100.00', 'date' => now()->toDateString(),
    ]);
    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'expense', 'account_id' => $account->id, 'category_id' => $rent->id, 'amount' => '50.00', 'date' => now()->toDateString(),
    ]);

    $status = app(BudgetService::class)->status($budget);
    expect($status['spent'])->toBe('100.00');
});

test('deleting an expense transaction updates budget spending back to zero', function () {
    [$user, $account] = financeUser();
    $budget = $user->budgets()->create([
        'name' => 'Overall',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '500.00',
    ]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'expense', 'account_id' => $account->id, 'amount' => '100.00', 'date' => now()->toDateString(),
    ]);

    expect(app(BudgetService::class)->status($budget)['spent'])->toBe('100.00');

    $tx = Transaction::where('user_id', $user->id)->first();
    $this->actingAs($user)->delete(route('transactions.destroy', $tx))->assertRedirect();

    expect(app(BudgetService::class)->status($budget)['spent'])->toBe('0.00')
        ->and((float) app(AccountBalanceService::class)->computeBalance($account->fresh()))->toBe(1000.0);
});

test('budget spending is isolated per user', function () {
    [$user, $account] = financeUser();
    $otherUser = User::factory()->create();
    $otherAccount = Account::create(['user_id' => $otherUser->id, 'name' => 'B', 'type' => 'bank', 'starting_balance' => '0.00', 'balance' => '0.00']);

    $budget = $user->budgets()->create([
        'name' => 'Overall',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '500.00',
    ]);

    // Other user's expense must not affect this user's budget.
    $this->actingAs($otherUser)->post(route('transactions.store'), [
        'type' => 'expense', 'account_id' => $otherAccount->id, 'amount' => '999.00', 'date' => now()->toDateString(),
    ]);

    expect(app(BudgetService::class)->status($budget)['spent'])->toBe('0.00');
});
