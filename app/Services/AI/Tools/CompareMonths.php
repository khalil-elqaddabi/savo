<?php

namespace App\Services\AI\Tools;

use App\Services\AI\FinancialTool;
use App\Services\FinancialAnalyticsService;
use Carbon\CarbonImmutable;

/**
 * Month-over-month comparison from deterministic analytics, including income,
 * expenses and per-category deltas. Never reports transfers.
 */
class CompareMonths implements FinancialTool
{
    public function __construct(private FinancialAnalyticsService $analytics)
    {
    }

    public function name(): string
    {
        return 'compareMonths';
    }

    public function description(): string
    {
        return 'Compare the current month with the previous month: income and expense deltas (percent and absolute) and a per-category expense comparison. Useful for "why did my spending increase?".';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => [], 'additionalProperties' => false];
    }

    public function run(int $userId, ?CarbonImmutable $reference, array $arguments = []): array
    {
        $ref = $reference ?? CarbonImmutable::now();

        $compare = $this->analytics->compareMonths($userId, $ref);

        $currentFrom = $ref->startOfMonth()->toDateString();
        $currentTo = $ref->endOfMonth()->toDateString();
        $prev = $ref->subMonth();
        $prevFrom = $prev->startOfMonth()->toDateString();
        $prevTo = $prev->endOfMonth()->toDateString();

        $categories = $this->analytics->categoryComparison($userId, $currentFrom, $currentTo, $prevFrom, $prevTo)
            ->map(fn ($c) => [
                'name' => $c['name'],
                'current' => (float) $c['current'],
                'previous' => (float) $c['previous'],
                'delta_percent' => $c['delta_percent'],
            ])
            ->take(10)
            ->values()
            ->all();

        return [
            'current_month' => [
                'income' => (float) $compare['current']['income'],
                'expenses' => (float) $compare['current']['expenses'],
            ],
            'previous_month' => [
                'income' => (float) $compare['previous']['income'],
                'expenses' => (float) $compare['previous']['expenses'],
            ],
            'income_delta_percent' => $compare['income_delta_percent'],
            'expense_delta_percent' => $compare['expense_delta_percent'],
            'expense_absolute_delta' => (float) $compare['expense_absolute_delta'],
            'category_comparison' => $categories,
        ];
    }
}
