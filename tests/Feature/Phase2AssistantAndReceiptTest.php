<?php

use App\Models\Account;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Debt;
use App\Models\User;
use App\Services\AI\FinancialToolRegistry;
use App\Services\ReceiptScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

function scanToolsUser(): User
{
    return User::factory()->create(['locale' => 'en', 'currency' => 'MAD']);
}

test('getBills tool reports bills, totals and upcoming payments scoped to the user', function () {
    $user = scanToolsUser();
    $other = scanToolsUser();

    $account = Account::create([
        'user_id' => $user->id,
        'name' => 'Checking',
        'type' => 'bank',
        'starting_balance' => '1000.00',
        'balance' => '1000.00',
    ]);

    Bill::create([
        'user_id' => $user->id,
        'name' => 'Netflix',
        'amount' => '19.99',
        'currency' => 'MAD',
        'frequency' => 'monthly',
        'interval' => 1,
        'status' => 'active',
        'next_payment_date' => now()->addDays(5)->toDateString(),
    ]);
    Bill::create([
        'user_id' => $user->id,
        'name' => 'Rent',
        'amount' => '4000.00',
        'currency' => 'MAD',
        'frequency' => 'monthly',
        'interval' => 1,
        'status' => 'active',
        'account_id' => $account->id,
        'next_payment_date' => now()->addDays(10)->toDateString(),
    ]);
    // Other user's bill must never leak into this user's results.
    Bill::create([
        'user_id' => $other->id,
        'name' => 'OtherBill',
        'amount' => '999.00',
        'currency' => 'MAD',
        'frequency' => 'monthly',
        'interval' => 1,
        'status' => 'active',
        'next_payment_date' => now()->addDays(3)->toDateString(),
    ]);

    $result = app(FinancialToolRegistry::class)->execute('getBills', $user->id);

    expect($result['bills'])->toHaveCount(2)
        ->and(collect($result['bills'])->pluck('name'))->not->toContain('OtherBill')
        ->and($result['total_monthly_cost'])->toBe(4019.99);
});

test('getDebts tool reports summary and debts scoped to the user', function () {
    $user = scanToolsUser();
    $other = scanToolsUser();

    Debt::create([
        'user_id' => $user->id,
        'name' => 'Car loan',
        'type' => 'loan',
        'original_amount' => '20000.00',
        'remaining_amount' => '15000.00',
        'frequency' => 'monthly',
        'installment_amount' => '500.00',
        'status' => 'active',
    ]);
    Debt::create([
        'user_id' => $other->id,
        'name' => 'Other debt',
        'type' => 'loan',
        'original_amount' => '5000.00',
        'remaining_amount' => '5000.00',
        'frequency' => 'monthly',
        'status' => 'active',
    ]);

    $result = app(FinancialToolRegistry::class)->execute('getDebts', $user->id);

    expect($result['summary']['total_remaining'])->toBe(15000.0)
        ->and($result['summary']['count'])->toBe(1)
        ->and(collect($result['debts'])->pluck('name'))->not->toContain('Other debt');
});

test('receipt scan degrades gracefully when AI is not configured', function () {
    // Force the AI key blank so no network call is attempted.
    config(['services.ai.api_key' => null]);

    $user = scanToolsUser();
    $file = UploadedFile::fake()->create('receipt.png', 200, 'image/png');

    $service = app(ReceiptScanService::class);

    try {
        $service->scan($user, $file);
        $this->fail('Expected RuntimeException when AI is not configured.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('not configured');
    }
});

test('receipt scan POST is guarded by validation when no file is sent', function () {
    $user = scanToolsUser();

    $this->actingAs($user)
        ->post('/receipt/scan', [])
        ->assertSessionHasErrors('receipt');
});
