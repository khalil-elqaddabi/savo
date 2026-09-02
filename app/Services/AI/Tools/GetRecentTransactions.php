<?php

namespace App\Services\AI\Tools;

use App\Models\Transaction;
use App\Services\AI\FinancialTool;
use Carbon\CarbonImmutable;

/**
 * Most recent transactions for the user.
 *
 * Only a small bounded set is returned and raw notes/recipients are excluded.
 * The model is told these figures are illustrative and not authoritative for
 * any calculation it might attempt.
 */
class GetRecentTransactions implements FinancialTool
{
    public function name(): string
    {
        return 'getRecentTransactions';
    }

    public function description(): string
    {
        return 'Get the most recent transactions (type, amount, date, category and account) to help answer questions about recent spending. Returns a bounded set; amounts are in MAD.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => ['type' => 'integer', 'description' => 'Maximum number of recent transactions to return (default 5, max 15).'],
            ],
            'additionalProperties' => false,
        ];
    }

    public function run(int $userId, ?CarbonImmutable $reference, array $arguments = []): array
    {
        $limit = max(1, min(15, (int) ($arguments['limit'] ?? 5)));

        $rows = Transaction::query()
            ->where('user_id', $userId)
            ->with('category:id,name', 'account:id,name')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'type', 'amount', 'date', 'category_id', 'account_id']);

        return [
            'transactions' => $rows->map(function ($t) {
                return [
                    'type' => $t->type,
                    'amount' => (float) $t->amount,
                    'date' => $t->date,
                    'category' => $t->category?->name,
                    'account' => $t->account?->name,
                ];
            })->values()->all(),
        ];
    }
}
