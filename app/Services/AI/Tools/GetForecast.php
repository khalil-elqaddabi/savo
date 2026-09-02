<?php

namespace App\Services\AI\Tools;

use App\Services\AI\FinancialTool;
use App\Services\ForecastService;
use Carbon\CarbonImmutable;

/**
 * End-of-month financial forecast from the deterministic forecast engine.
 */
class GetForecast implements FinancialTool
{
    public function __construct(private ForecastService $forecast)
    {
    }

    public function name(): string
    {
        return 'getForecast';
    }

    public function description(): string
    {
        return 'Get the end-of-month forecast: expected income, expected expenses, planned savings and projected balance for the current (or a given) month.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'month' => ['type' => 'string', 'format' => 'date', 'description' => 'Optional month (YYYY-MM) to forecast.'],
            ],
            'additionalProperties' => false,
        ];
    }

    public function run(int $userId, ?CarbonImmutable $reference, array $arguments = []): array
    {
        $month = $reference ?? CarbonImmutable::now();

        if ($monthArg = $arguments['month'] ?? null) {
            try {
                $month = CarbonImmutable::parse($monthArg);
            } catch (\Throwable) {
                // keep reference
            }
        }

        $f = $this->forecast->forecastForMonth($userId, $month);

        return [
            'month' => $f['month'],
            'current_balance' => (float) $f['current_balance'],
            'expected_income' => (float) $f['expected_income'],
            'expected_expenses' => (float) $f['expected_expenses'],
            'planned_savings' => (float) $f['planned_savings'],
            'projected_balance' => (float) $f['projected_balance'],
        ];
    }
}
