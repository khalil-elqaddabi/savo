<?php

namespace App\Services\AI\Tools;

use App\Services\AI\FinancialTool;
use App\Services\SafeToSpendService;
use Carbon\CarbonImmutable;
use App\Support\Money;

/**
 * How much the user can safely spend between now and the period end, from the
 * deterministic safe-to-spend engine.
 */
class GetSafeToSpend implements FinancialTool
{
    public function __construct(private SafeToSpendService $safeToSpend)
    {
    }

    public function name(): string
    {
        return 'getSafeToSpend';
    }

    public function description(): string
    {
        return 'Get how much the user can safely spend between now and the period end, including the daily allowance and upcoming obligations that are already protected.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => [], 'additionalProperties' => false];
    }

    public function run(int $userId, ?CarbonImmutable $reference, array $arguments = []): array
    {
        $safe = $this->safeToSpend->daily($userId, $reference);

        return [
            'total_safe_to_spend' => (float) $safe['safe_to_spend'],
            'daily_allowance' => (float) $safe['safe_to_spend_daily'],
            'protected_for_obligations' => (float) $safe['protected_money'],
            'planned_savings' => (float) $safe['planned_savings'],
            'period_end' => $safe['period_end'],
            'currency' => 'MAD',
        ];
    }
}
