<?php

namespace App\Services\AI\Tools;

use App\Services\AI\FinancialTool;
use App\Services\RecurringTransactionService;
use Carbon\CarbonImmutable;

/**
 * Upcoming recurring commitments (bills and income) computed by the recurring
 * engine. Only names, types, amounts and dates are exposed.
 */
class GetRecurringUpcoming implements FinancialTool
{
    public function __construct(private RecurringTransactionService $recurring)
    {
    }

    public function name(): string
    {
        return 'getRecurringUpcoming';
    }

    public function description(): string
    {
        return 'Get upcoming recurring transactions (future income and bills) within the next 60 days, with their dates and amounts.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => ['type' => 'integer', 'description' => 'Maximum number of upcoming items to return (default 10).'],
            ],
            'additionalProperties' => false,
        ];
    }

    public function run(int $userId, ?CarbonImmutable $reference, array $arguments = []): array
    {
        $limit = max(1, min(30, (int) ($arguments['limit'] ?? 10)));

        $upcoming = $this->recurring->upcomingForUser($userId, $limit)
            ->map(fn ($e) => [
                'date' => $e['date'],
                'name' => $e['recurring']['name'],
                'type' => $e['recurring']['type'],
                'amount' => (float) $e['recurring']['amount'],
                'frequency' => $e['recurring']['frequency'],
            ])
            ->values()
            ->all();

        return ['upcoming' => $upcoming];
    }
}
