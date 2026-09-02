<?php

namespace App\Services\AI\Tools;

use App\Services\AI\FinancialTool;
use App\Services\FinancialAnalyticsService;
use Carbon\CarbonImmutable;

/**
 * Month-to-date income, expenses, net and savings rate using deterministic
 * analytics. Transfers are never counted as income or spending.
 */
class GetFinancialSummary implements FinancialTool
{
    public function __construct(private FinancialAnalyticsService $analytics)
    {
    }

    public function name(): string
    {
        return 'getFinancialSummary';
    }

    public function description(): string
    {
        return 'Get the user\'s month-to-date financial summary: total income, total expenses, net result and savings rate for the current (or a given) month. Transfers are excluded.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'month' => [
                    'type' => 'string',
                    'format' => 'date',
                    'description' => 'Optional month (YYYY-MM) to summarize instead of the current one.',
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    public function run(int $userId, ?CarbonImmutable $reference, array $arguments = []): array
    {
        $ref = $this->resolveMonth($reference, $arguments['month'] ?? null);

        $summary = $this->analytics->summary(
            $userId,
            $ref->startOfMonth()->toDateString(),
            $ref->endOfMonth()->toDateString(),
        );

        return [
            'month' => $ref->format('Y-m'),
            'income' => (float) $summary['income'],
            'expenses' => (float) $summary['expenses'],
            'net' => (float) $summary['net'],
            'savings_rate_percent' => $summary['savings_rate'],
            'transaction_count' => $summary['transaction_count'],
            'average_daily_spend' => (float) $summary['avg_daily_spend'],
        ];
    }

    protected function resolveMonth(?CarbonImmutable $reference, ?string $month): CarbonImmutable
    {
        $base = $reference ?? CarbonImmutable::now();

        if ($month) {
            try {
                return CarbonImmutable::parse($month)->startOfMonth();
            } catch (\Throwable) {
                return $base;
            }
        }

        return $base;
    }
}
