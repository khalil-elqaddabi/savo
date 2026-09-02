<?php

namespace App\Services\AI\Tools;

use App\Services\AI\FinancialTool;
use App\Services\FinancialAnalyticsService;
use Carbon\CarbonImmutable;

/**
 * Spending broken down by category for a month, from deterministic analytics.
 * Only aggregated category figures are returned (no raw transactions).
 */
class GetCategorySpending implements FinancialTool
{
    public function __construct(private FinancialAnalyticsService $analytics)
    {
    }

    public function name(): string
    {
        return 'getCategorySpending';
    }

    public function description(): string
    {
        return 'Get spending broken down by category for the current (or a given) month, including the largest categories and their share of total spending.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'month' => [
                    'type' => 'string',
                    'format' => 'date',
                    'description' => 'Optional month (YYYY-MM) to analyse instead of the current one.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of top categories to return (default 8).',
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    public function run(int $userId, ?CarbonImmutable $reference, array $arguments = []): array
    {
        $ref = $reference ?? CarbonImmutable::now();
        $limit = max(1, min(20, (int) ($arguments['limit'] ?? 8)));

        $from = $ref->startOfMonth()->toDateString();
        $to = $ref->endOfMonth()->toDateString();

        $categories = $this->analytics->byCategory($userId, $from, $to)
            ->take($limit)
            ->map(fn ($c) => [
                'name' => $c['name'],
                'spent' => (float) $c['amount'],
                'share_percent' => $c['share'],
            ])
            ->values()
            ->all();

        return ['month' => $ref->format('Y-m'), 'categories' => $categories];
    }
}
