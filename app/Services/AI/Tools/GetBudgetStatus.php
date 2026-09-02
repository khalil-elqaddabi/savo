<?php

namespace App\Services\AI\Tools;

use App\Services\AI\FinancialTool;
use App\Services\BudgetService;
use Carbon\CarbonImmutable;

/**
 * Current budget status from the deterministic budget engine, including which
 * budget is closest to, or already over, its limit.
 */
class GetBudgetStatus implements FinancialTool
{
    public function __construct(private BudgetService $budgets)
    {
    }

    public function name(): string
    {
        return 'getBudgetStatus';
    }

    public function description(): string
    {
        return 'Get the status of every active budget: amount, spent, remaining, percent used and whether it is on track, at risk or exceeded.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => [], 'additionalProperties' => false];
    }

    public function run(int $userId, ?CarbonImmutable $reference, array $arguments = []): array
    {
        $rows = $this->budgets->currentOverview($userId, $reference)
            ->map(fn ($s) => [
                'name' => $s['budget']->name,
                'period' => $s['budget']->period,
                'category' => $s['budget']->category?->name,
                'amount' => (float) $s['amount'],
                'spent' => (float) $s['spent'],
                'remaining' => (float) $s['remaining'],
                'percent_used' => $s['percent'],
                'status' => $s['status'],
            ])
            ->sortByDesc('percent')
            ->values()
            ->all();

        return ['budgets' => $rows];
    }
}
