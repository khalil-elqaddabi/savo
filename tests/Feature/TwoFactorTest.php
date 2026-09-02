<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function actingConfirmed(User $user)
{
    return test()->session(['auth.password_confirmed_at' => now()->timestamp])->actingAs($user);
}

function enableTwoFactor(User $user): void
{
    actingConfirmed($user)->post('/user/two-factor-authentication');
}

function recoveryCodes(User $user): array
{
    $response = actingConfirmed($user)->get('/user/two-factor-recovery-codes')->assertOk();

    return json_decode($response->getContent(), true);
}

test('enabling two factor authentication stores an encrypted secret and recovery codes', function () {
    $user = User::factory()->create();

    enableTwoFactor($user);

    $user->refresh();

    expect($user->two_factor_secret)->not->toBeNull()
        ->and(str_contains($user->two_factor_secret, 'secret'))->toBeFalse()
        ->and($user->two_factor_recovery_codes)->not->toBeNull();
});

test('recovery codes are returned as a json array for an enabled user', function () {
    $user = User::factory()->create();
    enableTwoFactor($user);

    $codes = recoveryCodes($user);

    expect($codes)->toBeArray()
        ->toHaveCount(8)
        ->each(fn ($code) => $code->toBeString()->not->toBeEmpty());
});

test('recovery codes are empty when two factor authentication is disabled', function () {
    $user = User::factory()->create();

    actingConfirmed($user)
        ->get('/user/two-factor-recovery-codes')
        ->assertOk()
        ->assertJson([]);
});

test('regenerating recovery codes produces a different set', function () {
    $user = User::factory()->create();
    enableTwoFactor($user);

    $original = recoveryCodes($user);

    actingConfirmed($user)->post('/user/two-factor-recovery-codes');

    $regenerated = recoveryCodes($user);

    expect($regenerated)->toHaveCount(8)
        ->and($regenerated)->not->toEqual($original);
});

test('a second user cannot obtain another user recovery codes', function () {
    $owner = User::factory()->create();
    enableTwoFactor($owner);

    $ownerCodes = recoveryCodes($owner);

    $other = User::factory()->create();
    $otherCodes = recoveryCodes($other);

    expect($otherCodes)->not->toEqual($ownerCodes)
        ->and($otherCodes)->toBe([]);
});

test('recovery codes are never exposed through user serialization', function () {
    $user = User::factory()->create();
    enableTwoFactor($user);

    $user->refresh();

    $json = $user->toArray();

    expect(array_key_exists('two_factor_recovery_codes', $json))->toBeFalse()
        ->and(array_key_exists('two_factor_secret', $json))->toBeFalse();
});

test('disabling two factor authentication clears secret and recovery codes', function () {
    $user = User::factory()->create();
    enableTwoFactor($user);

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull();

    actingConfirmed($user)->delete('/user/two-factor-authentication');

    $user->refresh();

    expect($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull();

    actingConfirmed($user)
        ->get('/user/two-factor-recovery-codes')
        ->assertOk()
        ->assertJson([]);
});

test('guests cannot access the recovery codes endpoint', function () {
    $this->get('/user/two-factor-recovery-codes')->assertRedirect(route('login'));
});
