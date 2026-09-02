<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a user can create an account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('accounts.store'), [
        'name' => 'My Wallet',
        'type' => 'digital_wallet',
        'balance' => '250.00',
    ]);

    $account = $user->accounts()->first();
    expect($account->name)->toBe('My Wallet')
        ->and($account->type)->toBe('digital_wallet');
});

test('creating an account requires a name and valid type', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('accounts.store'), ['name' => '', 'type' => 'bad'])
        ->assertSessionHasErrors(['name', 'type']);

    expect($user->accounts()->count())->toBe(0);
});

test('a user cannot update another user account', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $account = Account::create([
        'user_id' => $userA->id,
        'name' => 'Secret',
        'type' => 'bank',
        'starting_balance' => '0.00',
        'balance' => '0.00',
    ]);

    $this->actingAs($userB)
        ->put(route('accounts.update', $account), ['name' => 'Hacked', 'type' => 'bank'])
        ->assertForbidden();

    expect($account->fresh()->name)->toBe('Secret');
});

test('a user cannot delete another user account', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $account = Account::create([
        'user_id' => $userA->id,
        'name' => 'Secret',
        'type' => 'bank',
        'starting_balance' => '0.00',
        'balance' => '0.00',
    ]);

    $this->actingAs($userB)
        ->delete(route('accounts.destroy', $account))
        ->assertForbidden();

    expect(Account::find($account->id))->not->toBeNull();
});

test('a user can delete their own account', function () {
    $user = User::factory()->create();

    $account = Account::create([
        'user_id' => $user->id,
        'name' => 'Mine',
        'type' => 'cash',
        'starting_balance' => '10.00',
        'balance' => '10.00',
    ]);

    $this->actingAs($user)
        ->delete(route('accounts.destroy', $account))
        ->assertRedirect(route('accounts.index'));

    expect(Account::find($account->id))->toBeNull();
});
