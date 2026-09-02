<?php

namespace App\Services\AI\Tools;

use App\Services\AccountBalanceService;
use App\Services\AI\FinancialTool;
use Carbon\CarbonImmutable;

/**
 * Current balances per active account plus the total balance, computed by the
 * deterministic balance engine. Only account names and balances are exposed.
 */
class GetAccountBalances implements FinancialTool
{
    public function __construct(private AccountBalanceService $balances)
    {
    }

    public function name(): string
    {
        return 'getAccountBalances';
    }

    public function description(): string
    {
        return 'Get the current balance of every active account plus the total balance across all accounts.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => [], 'additionalProperties' => false];
    }

    public function run(int $userId, ?CarbonImmutable $reference, array $arguments = []): array
    {
        $accounts = \App\Models\Account::query()
            ->where('user_id', $userId)
            ->where('is_archived', false)
            ->get(['id', 'user_id', 'name', 'type', 'starting_balance', 'balance'])
            ->map(fn ($a) => [
                'name' => $a->name,
                'type' => $a->type,
                'balance' => (float) $this->balances->computeBalance($a),
            ])
            ->values()
            ->all();

        return [
            'total_balance' => (float) $this->balances->totalBalance($userId),
            'accounts' => $accounts,
        ];
    }
}
