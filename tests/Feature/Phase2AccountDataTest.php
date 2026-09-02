<?php

use App\Models\Account;
use App\Models\Bill;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Debt;
use App\Models\Notification;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function phase2User(array $opts = []): array
{
    $opts = array_merge(['password' => 'secret-password', 'currency' => 'MAD'], $opts);
    $user = User::factory()->create($opts);

    $account = Account::create([
        'user_id' => $user->id,
        'name' => 'Checking',
        'type' => 'bank',
        'starting_balance' => '1000.00',
        'balance' => '1000.00',
    ]);

    return [$user, $account];
}

function phase2SystemCategory(): Category
{
    return Category::create([
        'user_id' => null,
        'name' => 'Groceries',
        'type' => 'expense',
        'is_system' => true,
        'is_active' => true,
    ]);
}

test('reset deletes only the authenticated user data and keeps the account and system categories', function () {
    [$user, $account] = phase2User();
    phase2SystemCategory();

    Category::create([
        'user_id' => $user->id,
        'name' => 'Custom',
        'type' => 'expense',
        'is_system' => false,
    ]);
    Transaction::create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'type' => 'expense',
        'amount' => '25.00',
        'date' => now(),
        'description' => 'Coffee',
    ]);
    Budget::create([
        'user_id' => $user->id,
        'name' => 'Fun',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '100.00',
    ]);
    SavingsGoal::create([
        'user_id' => $user->id,
        'name' => 'Trip',
        'target_amount' => '1000.00',
        'current_amount' => '100.00',
    ]);
    Bill::create([
        'user_id' => $user->id,
        'name' => 'Netflix',
        'amount' => '50.00',
        'currency' => 'MAD',
        'frequency' => 'monthly',
        'next_payment_date' => now()->addDays(2)->toDateString(),
        'status' => 'active',
    ]);
    Debt::create([
        'user_id' => $user->id,
        'name' => 'Loan',
        'type' => 'loan',
        'original_amount' => '500.00',
        'remaining_amount' => '300.00',
        'status' => 'active',
    ]);
    Notification::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'notifiable_id' => $user->id,
        'notifiable_type' => User::class,
        'type' => 'App\Notifications\SmartNotification',
        'kind' => 'budget_alert',
        'title' => 'Alert',
        'message' => 'Hi',
    ]);

    [$other, $otherAccount] = phase2User();
    Transaction::create([
        'user_id' => $other->id,
        'account_id' => $otherAccount->id,
        'type' => 'expense',
        'amount' => '9.00',
        'date' => now(),
        'description' => "Other's txn",
    ]);

    $this->actingAs($user)
        ->post('/data/reset', ['current_password' => 'secret-password'])
        ->assertSessionHas('success');

    $this->assertDatabaseCount('transactions', 1); // only the other user's remains
    expect(Transaction::where('user_id', $user->id)->count())->toBe(0)
        ->and(Account::where('user_id', $user->id)->count())->toBe(0)
        ->and(Budget::where('user_id', $user->id)->count())->toBe(0)
        ->and(SavingsGoal::where('user_id', $user->id)->count())->toBe(0)
        ->and(Bill::where('user_id', $user->id)->count())->toBe(0)
        ->and(Debt::where('user_id', $user->id)->count())->toBe(0)
        ->and(Notification::where('notifiable_id', $user->id)->count())->toBe(0)
        ->and(Category::where('user_id', $user->id)->count())->toBe(0)
        ->and(Category::whereNull('user_id')->count())->toBeGreaterThan(0) // system categories preserved
        ->and(User::find($user->id)->exists())->toBeTrue();

    // Account re-seeded to clean state, still usable as an authenticated user
    $this->get('/dashboard')->assertOk();
});

test('reset requires the correct password and rejects a wrong one', function () {
    [$user] = phase2User();

    $this->actingAs($user)
        ->post('/data/reset', ['current_password' => 'wrong-password'])
        ->assertSessionHasErrors('current_password');

    $this->assertDatabaseCount('notifications', 0);
});

test('reset never touches another users data', function () {
    [$user, $account] = phase2User();
    [$other, $otherAccount] = phase2User();

    Transaction::create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'type' => 'expense',
        'amount' => '10.00',
        'date' => now(),
        'description' => 'Mine',
    ]);
    Transaction::create([
        'user_id' => $other->id,
        'account_id' => $otherAccount->id,
        'type' => 'expense',
        'amount' => '20.00',
        'date' => now(),
        'description' => 'Theirs',
    ]);

    $this->actingAs($user)
        ->post('/data/reset', ['current_password' => 'secret-password'])
        ->assertSessionHas('success');

    expect(Transaction::where('user_id', $user->id)->count())->toBe(0)
        ->and(Transaction::where('user_id', $other->id)->count())->toBe(1)
        ->and(Account::where('user_id', $other->id)->count())->toBe(1);
});

test('csv export streams a csv of the users transactions', function () {
    [$user, $account] = phase2User();

    Transaction::create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'type' => 'expense',
        'amount' => '25.50',
        'date' => now(),
        'description' => 'Coffee',
    ]);

    $response = $this->actingAs($user)->get('/data/export');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $body = $response->streamedContent();
    expect($body)->toContain('date,type,amount')
        ->and($body)->toContain('Coffee')
        ->and($body)->toContain('25.50');
});

test('csv import creates transactions scoped to the user', function () {
    [$user] = phase2User();

    $csv = implode("\n", [
        'date,type,amount,description,merchant,account,destination,category',
        '2026-01-05,expense,12.50,Groceries,Store,Checking,,Food',
        '2026-01-06,income,100.00,Salary,,Checking,,',
    ]);

    $this->actingAs($user)
        ->post('/data/import', ['file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('import.csv', $csv)])
        ->assertSessionHas('success');

    expect(Transaction::where('user_id', $user->id)->count())->toBe(2)
        ->and(Transaction::where('user_id', $user->id)->where('type', 'expense')->first()->amount)->toBe('12.50')
        ->and(Category::where('user_id', $user->id)->where('name', 'Food')->exists())->toBeTrue();
});

function phase2OAuthUser(): User
{
    $user = User::factory()->create([
        'password' => \Illuminate\Support\Str::random(80),
        'currency' => 'MAD',
    ]);

    \App\Models\OAuthIdentity::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_user_id' => (string) \Illuminate\Support\Str::uuid(),
        'nickname' => $user->name,
    ]);

    return $user;
}

test('google oauth user can reset their own data without a current password', function () {
    $user = phase2OAuthUser();

    $account = Account::create([
        'user_id' => $user->id,
        'name' => 'Checking',
        'type' => 'bank',
        'starting_balance' => '1000.00',
        'balance' => '1000.00',
    ]);
    Transaction::create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'type' => 'expense',
        'amount' => '25.00',
        'date' => now(),
        'description' => 'OAuth txn',
    ]);

    $this->actingAs($user)
        ->post('/data/reset') // no current_password
        ->assertSessionHas('success');

    expect(Transaction::where('user_id', $user->id)->count())->toBe(0)
        ->and(Account::where('user_id', $user->id)->count())->toBe(0)
        ->and(User::find($user->id)->exists())->toBeTrue();
});

test('oauth reset never touches another users data and preserves the account', function () {
    [$owner, $ownerAccount] = phase2User();
    $member = phase2OAuthUser();

    $budget = Budget::create([
        'user_id' => $owner->id,
        'name' => 'Shared',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '200.00',
    ]);
    \App\Models\BudgetShare::create([
        'budget_id' => $budget->id,
        'user_id' => $member->id,
        'role' => 'viewer',
    ]);

    Transaction::create([
        'user_id' => $member->id,
        'account_id' => $ownerAccount->id,
        'type' => 'expense',
        'amount' => '5.00',
        'date' => now(),
        'description' => 'member txn',
    ]);

    $this->actingAs($member)
        ->post('/data/reset')
        ->assertSessionHas('success');

    // member data gone, owner budget intact, member's share membership removed
    expect(Transaction::where('user_id', $member->id)->count())->toBe(0)
        ->and(Transaction::where('user_id', $owner->id)->count())->toBe(0)
        ->and(Budget::where('user_id', $owner->id)->count())->toBe(1)
        ->and(\App\Models\BudgetShare::where('budget_id', $budget->id)->count())->toBe(0);
});

test('resetting the owner deletes owned budgets and their shared rows', function () {
    $owner = phase2User()[0];
    $member = phase2User()[0];

    $budget = Budget::create([
        'user_id' => $owner->id,
        'name' => 'Owned',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '100.00',
    ]);
    \App\Models\BudgetShare::create([
        'budget_id' => $budget->id,
        'user_id' => $member->id,
        'role' => 'viewer',
    ]);

    $this->actingAs($owner)
        ->post('/data/reset', ['current_password' => 'secret-password'])
        ->assertSessionHas('success');

    expect(Budget::where('user_id', $owner->id)->count())->toBe(0)
        ->and(\App\Models\BudgetShare::where('budget_id', $budget->id)->count())->toBe(0)
        ->and($member->exists)->toBeTrue();
});

test('reset scenario A: owner resetting deletes owned budget and its shares but not the members data', function () {
    [$owner, $ownerAccount] = phase2User();
    [$member, $memberAccount] = phase2User();

    $budget = Budget::create([
        'user_id' => $owner->id,
        'name' => 'Shared with B',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '200.00',
    ]);
    \App\Models\BudgetShare::create([
        'budget_id' => $budget->id,
        'user_id' => $member->id,
        'role' => 'editor',
    ]);

    Transaction::create([
        'user_id' => $member->id,
        'account_id' => $memberAccount->id,
        'type' => 'expense',
        'amount' => '33.00',
        'date' => now(),
        'description' => "B's unrelated expense",
    ]);

    $this->actingAs($owner)
        ->post('/data/reset', ['current_password' => 'secret-password'])
        ->assertSessionHas('success');

    // Owner's budget and its shares are gone.
    expect(Budget::where('id', $budget->id)->count())->toBe(0)
        ->and(\App\Models\BudgetShare::where('budget_id', $budget->id)->count())->toBe(0)
        // Member B still exists and their own financial data is untouched.
        ->and($member->exists)->toBeTrue()
        ->and(Transaction::where('user_id', $member->id)->count())->toBe(1)
        ->and(Account::where('user_id', $member->id)->count())->toBe(1);
});

test('reset scenario B: a member resetting removes their share membership but keeps the owners budget', function () {
    [$owner] = phase2User();
    [$member] = phase2User();

    $budget = Budget::create([
        'user_id' => $owner->id,
        'name' => "Owner's budget",
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '200.00',
    ]);
    \App\Models\BudgetShare::create([
        'budget_id' => $budget->id,
        'user_id' => $member->id,
        'role' => 'viewer',
    ]);

    $this->actingAs($member)
        ->post('/data/reset', ['current_password' => 'secret-password'])
        ->assertSessionHas('success');

    // Member's share membership is removed.
    expect(\App\Models\BudgetShare::where('user_id', $member->id)->count())->toBe(0)
        // Owner's budget and its other state remain untouched.
        ->and(Budget::where('id', $budget->id)->count())->toBe(1)
        ->and(Budget::where('user_id', $owner->id)->count())->toBe(1)
        ->and($owner->exists)->toBeTrue();
});

test('reset scenario C: resetting removes all owned data and all of the users share memberships only', function () {
    [$ownerA] = phase2User();
    [$memberB] = phase2User();
    $otherOwner = phase2User()[0];

    // A owns a budget shared with B.
    $ownedByA = Budget::create([
        'user_id' => $ownerA->id,
        'name' => "A's budget",
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '100.00',
    ]);
    \App\Models\BudgetShare::create([
        'budget_id' => $ownedByA->id,
        'user_id' => $memberB->id,
        'role' => 'viewer',
    ]);

    // A is ALSO a member of another user's budget.
    $ownedByOther = Budget::create([
        'user_id' => $otherOwner->id,
        'name' => "Other's budget",
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '300.00',
    ]);
    \App\Models\BudgetShare::create([
        'budget_id' => $ownedByOther->id,
        'user_id' => $ownerA->id,
        'role' => 'editor',
    ]);

    $this->actingAs($ownerA)
        ->post('/data/reset', ['current_password' => 'secret-password'])
        ->assertSessionHas('success');

    // A's own budget (and its shares) are gone.
    expect(Budget::where('user_id', $ownerA->id)->count())->toBe(0)
        ->and(\App\Models\BudgetShare::where('budget_id', $ownedByA->id)->count())->toBe(0)
        // A's membership in the other user's budget is gone.
        ->and(\App\Models\BudgetShare::where('user_id', $ownerA->id)->count())->toBe(0)
        // The other owner's budget remains, with A's removed membership gone.
        ->and(Budget::where('id', $ownedByOther->id)->count())->toBe(1)
        ->and(\App\Models\BudgetShare::where('budget_id', $ownedByOther->id)->count())->toBe(0);
});
