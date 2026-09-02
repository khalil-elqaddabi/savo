<?php

namespace App\Services\AI\Tools;

use App\Services\AI\FinancialTool;
use App\Services\DebtService;
use Carbon\CarbonImmutable;

/**
 * Debts & loans summary: what the user owes, what is owed to them, and their
 * scheduled monthly payments.
 */
class GetDebts implements FinancialTool
{
    public function __construct(private DebtService $debts)
    {
    }

    public function name(): string
    {
        return 'getDebts';
    }

    public function description(): string
    {
        return 'Get the user\'s debts and loans: total remaining, monthly payments, what is owed to the user, progress, and each debt\'s status and next payment.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => [], 'additionalProperties' => false];
    }

    public function run(int $userId, ?CarbonImmutable $reference, array $arguments = []): array
    {
        $summary = $this->debts->summary($userId);

        return [
            'summary' => [
                'total_remaining' => (float) $summary['total_remaining'],
                'total_original' => (float) $summary['total_original'],
                'monthly_payments' => (float) $summary['monthly_payments'],
                'owed_to_user' => (float) $summary['owed_to_user'],
                'progress' => (float) $summary['progress'],
                'count' => $summary['count'],
            ],
            'debts' => $this->debts->all($userId)->all(),
        ];
    }
}
