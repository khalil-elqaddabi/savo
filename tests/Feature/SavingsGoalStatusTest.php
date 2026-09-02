<?php

use App\Models\SavingsGoal;
use App\Models\User;
use App\Services\SavingsGoalService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function goal(User $user, array $overrides = []): SavingsGoal
{
    return SavingsGoal::create(array_merge([
        'user_id' => $user->id,
        'name' => 'Trip',
        'target_amount' => '1000.00',
        'current_amount' => '400.00',
        'deadline' => null,
    ], $overrides));
}

test('days remaining is zero once the deadline has passed', function () {
    $user = User::factory()->create();
    $g = goal($user, ['deadline' => '2025-01-10']);

    $status = app(SavingsGoalService::class)->status($g, CarbonImmutable::parse('2025-02-01'));

    expect($status['days_remaining'])->toBe(0);
});

test('a past-due goal reports no required future contributions', function () {
    $user = User::factory()->create();
    $g = goal($user, ['deadline' => '2025-01-10']);

    $status = app(SavingsGoalService::class)->status($g, CarbonImmutable::parse('2025-02-01'));

    // With no time left you cannot still be told to save for a passed deadline.
    expect($status['required_daily'])->toBeNull()
        ->and($status['required_weekly'])->toBeNull()
        ->and($status['required_monthly'])->toBeNull();
});

test('remaining and progress are computed correctly', function () {
    $user = User::factory()->create();
    $g = goal($user, ['deadline' => '2026-01-10']);

    $status = app(SavingsGoalService::class)->status($g, CarbonImmutable::parse('2025-02-01'));

    expect($status['remaining'])->toBe('600.00')
        ->and($status['saved'])->toBe('400.00')
        ->and($status['progress_percent'])->toBe(40.0)
        ->and($status['days_remaining'])->toBeGreaterThan(0);
});

test('a completed goal reports on track and zero remaining', function () {
    $user = User::factory()->create();
    $g = goal($user, ['target_amount' => '400.00', 'current_amount' => '400.00']);

    $status = app(SavingsGoalService::class)->status($g, CarbonImmutable::parse('2025-02-01'));

    expect($status['remaining'])->toBe('0.00')
        ->and($status['progress_percent'])->toBe(100.0)
        ->and($status['on_track'])->toBeTrue()
        ->and($status['projected_completion'])->not->toBeNull();
});
