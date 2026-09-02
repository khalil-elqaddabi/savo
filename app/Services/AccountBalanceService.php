<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Computes account balances from the transaction ledger.
 *
 * The transaction ledger is the source of truth. An account's balance is
 * always recomputed from history to guarantee determinism, then the stored
 * `accounts.balance` column is refreshed for fast reads.
 */
class AccountBalanceService
{
    /**
     * Compute the canonical balance for a single account.
     *
     * balance = starting_balance
     *         + sum(income into this account)
     *         - sum(expense from this account)
     *         + sum(transfers in)
     *         - sum(transfers out)
     */
    public function computeBalance(Account $account): string
    {
        $accountId = (int) $account->id;
        $userId = (int) $account->user_id;

        $income = Transaction::query()
            ->where('user_id', $userId)
            ->where('account_id', $accountId)
            ->where('type', Transaction::TYPE_INCOME)
            ->sum('amount');

        $expense = Transaction::query()
            ->where('user_id', $userId)
            ->where('account_id', $accountId)
            ->where('type', Transaction::TYPE_EXPENSE)
            ->sum('amount');

        $transferOut = Transaction::query()
            ->where('user_id', $userId)
            ->where('account_id', $accountId)
            ->where('type', Transaction::TYPE_TRANSFER)
            ->sum('amount');

        $transferIn = Transaction::query()
            ->where('user_id', $userId)
            ->where('destination_account_id', $accountId)
            ->where('type', Transaction::TYPE_TRANSFER)
            ->sum('amount');

        $balance = Money::add($account->starting_balance, $income ?? 0, $transferIn ?? 0);
        $balance = Money::sub($balance, $expense ?? 0);
        $balance = Money::sub($balance, $transferOut ?? 0);

        return $balance;
    }

    public function refresh(Account|int $account): string
    {
        $account = $account instanceof Account ? $account : Account::query()->findOrFail($account);

        $balance = $this->computeBalance($account);
        $account->balance = $balance;
        $account->saveQuietly();

        return $balance;
    }

    /**
     * Recompute and persist balances for all accounts of a user.
     *
     * @return array<int, string> accountId => balance
     */
    public function refreshAllForUser(int $userId): array
    {
        $balances = [];
        Account::query()->where('user_id', $userId)->get()->each(function (Account $account) use (&$balances) {
            $balances[$account->id] = $this->refresh($account);
        });

        return $balances;
    }

    public function totalBalance(int $userId): string
    {
        $accounts = Account::query()
            ->where('user_id', $userId)
            ->where('is_archived', false)
            ->get(['id', 'balance']);

        return Money::add(...$accounts->map(fn ($a) => $a->balance)->all());
    }

    /**
     * Balance history across all non-archived accounts, bucketed per day.
     *
     * Every day from $from to $to (inclusive) is returned. The recorded
     * balance for a date is the end-of-day balance across the aggregate of
     * accounts, regardless of whether transactions occurred on that day.
     * Transfers between two in-scope accounts cancel out (they do not change
     * the aggregate balance), so they are never double counted.
     *
     * @return Collection<int, array{date: string, balance: string}>
     */
    public function balanceHistory(int $userId, string $from, string $to): Collection
    {
        $fromDate = CarbonImmutable::parse($from)->startOfDay();
        $toDate = CarbonImmutable::parse($to)->startOfDay();

        if ($toDate->lt($fromDate)) {
            return collect();
        }

        $accounts = Account::query()
            ->where('user_id', $userId)
            ->where('is_archived', false)
            ->get(['id', 'starting_balance']);

        $accountIds = $accounts->pluck('id')->map(fn ($id) => (int) $id)->all();

        $transactions = Transaction::query()
            ->where('user_id', $userId)
            ->where('date', '<=', $toDate)
            ->orderBy('date')
            ->orderBy('id')
            ->get(['date', 'account_id', 'destination_account_id', 'type', 'amount']);

        // Group the ledger by day, computing each day's net delta in integer cents.
        $dailyDeltas = [];
        foreach ($transactions as $t) {
            $d = CarbonImmutable::parse($t->date)->startOfDay()->toDateString();
            $dailyDeltas[$d] = ($dailyDeltas[$d] ?? 0) + $this->ledgerDelta($t, $accountIds);
        }

        // Running aggregate balance in cents, seeded with all starting balances
        // and all ledger activity that precedes the requested window.
        $cursor = 0;
        foreach ($accounts as $a) {
            $cursor += Money::toCents($a->starting_balance);
        }
        foreach ($dailyDeltas as $d => $delta) {
            if ($d < $fromDate->toDateString()) {
                $cursor += $delta;
            }
        }

        $history = [];
        for ($day = $fromDate; $day->lte($toDate); $day = $day->addDay()) {
            $key = $day->toDateString();
            $cursor += $dailyDeltas[$key] ?? 0;
            $history[] = ['date' => $key, 'balance' => Money::fromCents($cursor)];
        }

        return collect($history);
    }

    /**
     * Net effect (in integer cents) of a single ledger entry on the aggregate
     * balance of the in-scope (non-archived) accounts.
     *
     * @param int[] $accountIds
     */
    private function ledgerDelta(Transaction $t, array $accountIds): int
    {
        $amount = Money::toCents($t->amount);

        if ($t->type === Transaction::TYPE_INCOME) {
            return in_array((int) $t->account_id, $accountIds, true) ? $amount : 0;
        }

        if ($t->type === Transaction::TYPE_EXPENSE) {
            return in_array((int) $t->account_id, $accountIds, true) ? -$amount : 0;
        }

        if ($t->type === Transaction::TYPE_TRANSFER) {
            $in = in_array((int) $t->destination_account_id, $accountIds, true);
            $out = in_array((int) $t->account_id, $accountIds, true);

            if ($in && $out) {
                return 0; // internal transfer, aggregate unchanged
            }
            if ($in) {
                return $amount; // moved from an out-of-scope account
            }
            if ($out) {
                return -$amount; // moved to an out-of-scope account
            }

            return 0;
        }

        return 0;
    }
}
