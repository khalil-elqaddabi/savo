<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Regression: transaction create/delete return a redirect (back), so the
| Inertia SPA performs a full page swap and the list is refreshed without a
| manual reload (BUG 2/BUG 3 — the Transactions Add button was dead and the
| page could appear stale).
|--------------------------------------------------------------------------
*/

test('REG-D: creating a transaction redirects back instead of returning a plain JSON 200', function () {
    $user = User::factory()->create();
    $account = Account::create(['user_id' => $user->id, 'name' => 'Bank', 'type' => 'bank', 'starting_balance' => '0.00', 'balance' => '0.00']);

    $response = $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'expense',
        'account_id' => $account->id,
        'amount' => '42.00',
        'date' => now()->toDateString(),
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
    expect($user->transactions()->count())->toBe(1);
});

test('REG-D2: deleting a transaction redirects back so the SPA list stays in sync', function () {
    $user = User::factory()->create();
    $account = Account::create(['user_id' => $user->id, 'name' => 'Bank', 'type' => 'bank', 'starting_balance' => '0.00', 'balance' => '0.00']);
    $tx = $user->transactions()->create([
        'account_id' => $account->id,
        'type' => 'expense',
        'amount' => '12.00',
        'date' => now()->toDateString(),
        'description' => 'Coffee',
    ]);

    $this->actingAs($user)->delete(route('transactions.destroy', $tx))
        ->assertRedirect();

    expect($user->transactions()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Regression: account update persists and redirects back (BUG 3 — the dialog
| only closes on success, so the returned props must reflect the update).
|--------------------------------------------------------------------------
*/

test('REG-E: updating an account persists changes and redirects back', function () {
    $user = User::factory()->create();
    $account = Account::create(['user_id' => $user->id, 'name' => 'Bank', 'type' => 'bank', 'starting_balance' => '0.00', 'balance' => '0.00']);

    $this->actingAs($user)->put(route('accounts.update', $account), [
        'name' => 'Main Savings',
        'type' => 'savings',
        'description' => 'Updated',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($account->fresh()->name)->toBe('Main Savings')
        ->and($account->fresh()->type)->toBe('savings');
});

/*
|--------------------------------------------------------------------------
| Regression: validation failures surface an error bag instead of silently
| closing the dialog (BUG 3 — the UI kept open + displays errors on 422).
|--------------------------------------------------------------------------
*/

test('REG-E2: an invalid account create returns a 302 with a validation error bag', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('accounts.store'), [
        'name' => '',
        'type' => 'bank',
    ])->assertRedirect()->assertSessionHasErrors('name');

    expect($user->accounts()->count())->toBe(0);
});