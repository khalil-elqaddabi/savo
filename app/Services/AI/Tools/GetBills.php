<?php

namespace App\Services\AI\Tools;

use App\Models\Bill;
use App\Services\AI\FinancialTool;
use App\Services\BillService;
use Carbon\CarbonImmutable;

/**
 * Current bills & subscriptions: cost, frequency and upcoming payments.
 */
class GetBills implements FinancialTool
{
    public function __construct(private BillService $bills)
    {
    }

    public function name(): string
    {
        return 'getBills';
    }

    public function description(): string
    {
        return 'Get the user\'s active bills and subscriptions with their amount, currency, frequency, status and upcoming payment, plus the total monthly and yearly cost.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => [], 'additionalProperties' => false];
    }

    public function run(int $userId, ?CarbonImmutable $reference, array $arguments = []): array
    {
        $items = Bill::query()
            ->where('user_id', $userId)
            ->with('category')
            ->orderByRaw(
                "CASE status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 WHEN 'cancelled' THEN 2 ELSE 3 END"
            )
            ->orderBy('next_payment_date')
            ->get()
            ->map(fn (Bill $b) => [
                'name' => $b->name,
                'amount' => (float) $b->amount,
                'currency' => $b->currency,
                'frequency' => $b->frequency,
                'next_payment_date' => $b->next_payment_date?->toDateString(),
                'status' => $b->status,
                'category' => $b->category?->name,
                'monthly_cost' => (float) $this->bills->monthlyAmount($b),
            ])
            ->values()
            ->all();

        return [
            'bills' => $items,
            'total_monthly_cost' => (float) $this->bills->monthlyCost($userId, $reference),
            'total_yearly_cost' => (float) $this->bills->yearlyCost($userId),
            'upcoming' => $this->bills->upcoming($userId, limit: 6, from: $reference)
                ->map(fn ($e) => [
                    'date' => $e['date'],
                    'name' => $e['bill']['name'],
                    'amount' => (float) $e['bill']['amount'],
                    'currency' => $e['bill']['currency'],
                ])
                ->values()
                ->all(),
        ];
    }
}
