<?php

namespace App\Services\AI\Tools;

use App\Services\AccountBalanceService;
use App\Services\AI\FinancialTool;
use App\Services\SafeToSpendService;
use Carbon\CarbonImmutable;
use App\Support\Money;

/**
 * Server-side affordability check.
 *
 * Money safety: the application computes the affordability verdict
 * deterministically; the model is only asked to explain it. The model is never
 * treated as the authoritative calculator.
 */
class GetAffordability implements FinancialTool
{
    public function __construct(
        private SafeToSpendService $safeToSpend,
        private AccountBalanceService $balances,
    ) {
    }

    public function name(): string
    {
        return 'getAffordability';
    }

    public function description(): string
    {
        return 'Check whether a given amount can currently be afforded, comparing it against safe-to-spend and total balance. Returns a deterministic verdict the assistant should explain (not recompute).';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'amount' => [
                    'type' => 'number',
                    'description' => 'The amount in MAD (DH) to check, e.g. 10000.',
                ],
            ],
            'required' => ['amount'],
            'additionalProperties' => false,
        ];
    }

    public function run(int $userId, ?CarbonImmutable $reference, array $arguments = []): array
    {
        $amount = (float) ($arguments['amount'] ?? 0);
        $amount = max(0, $amount);

        $reference ??= CarbonImmutable::now();

        $safe = $this->safeToSpend->daily($userId, $reference);
        $safeToSpend = Money::toCents($safe['safe_to_spend']);
        $totalBalance = Money::toCents($this->balances->totalBalance($userId));

        $amountCents = Money::toCents((string) $amount);

        if ($amountCents <= $safeToSpend) {
            $verdict = 'affordable_now';
            $explanation = 'The amount is within the current safe-to-spend budget.';
        } elseif ($amountCents <= $totalBalance) {
            $verdict = 'affordable_from_savings';
            $explanation = 'Not within safe-to-spend, but covered by the total balance (would draw on money already set aside for other purposes).';
        } else {
            $verdict = 'not_affordable';
            $explanation = 'The amount exceeds the total available balance and cannot be afforded.';
        }

        return [
            'amount' => (float) $amount,
            'safe_to_spend' => (float) $safe['safe_to_spend'],
            'total_balance' => (float) $this->balances->totalBalance($userId),
            'period_end' => $safe['period_end'],
            'verdict' => $verdict,
            'explanation' => $explanation,
            'currency' => 'MAD',
        ];
    }
}
