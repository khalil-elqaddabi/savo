<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Records income and expense transactions and maintains account balances.
 * All mutations happen inside a database transaction.
 */
class TransactionService
{
    public function __construct(private AccountBalanceService $balances)
    {
    }

    public function createIncome(Account $account, array $data): Transaction
    {
        return $this->record($account, Transaction::TYPE_INCOME, $data);
    }

    public function createExpense(Account $account, array $data): Transaction
    {
        return $this->record($account, Transaction::TYPE_EXPENSE, $data);
    }

    private function record(Account $account, string $type, array $data): Transaction
    {
        return DB::transaction(function () use ($account, $type, $data) {
            $amount = Money::fromCents(Money::toCents($data['amount']));

            if (Money::compare($amount, 0) < 0) {
                $amount = Money::fromCents(-Money::toCents($amount));
            }

            $transaction = Transaction::create([
                'user_id' => $account->user_id,
                'account_id' => $account->id,
                'category_id' => $data['category_id'] ?? null,
                'type' => $type,
                'amount' => $amount,
                'date' => $data['date'] ?? now()->toDateString(),
                'description' => $data['description'] ?? null,
                'merchant' => $data['merchant'] ?? null,
                'is_transfer' => false,
            ]);

            $this->balances->refresh($account);

            return $transaction;
        });
    }

    public function update(Transaction $transaction, Account $account, array $data): Transaction
    {
        return DB::transaction(function () use ($transaction, $account, $data) {
            $amount = Money::fromCents(Money::toCents($data['amount']));
            if (Money::compare($amount, 0) < 0) {
                $amount = Money::fromCents(-Money::toCents($amount));
            }

            $oldAccountId = (int) $transaction->getOriginal('account_id');
            $oldDestinationId = (int) $transaction->getOriginal('destination_account_id');

            $type = $data['type'] ?? $transaction->type;
            $isTransfer = $type === Transaction::TYPE_TRANSFER;
            $destinationId = $isTransfer
                ? (int) ($data['destination_account_id'] ?? $transaction->destination_account_id)
                : null;

            $transaction->fill([
                'type' => $type,
                'account_id' => $account->id,
                'destination_account_id' => $destinationId,
                'category_id' => $data['category_id'] ?? $transaction->category_id,
                'amount' => $amount,
                'date' => $data['date'] ?? $transaction->date,
                'description' => $data['description'] ?? $transaction->description,
                'merchant' => $data['merchant'] ?? $transaction->merchant,
                'is_transfer' => $isTransfer,
            ])->save();

            // Refresh every account whose balance this transaction may have
            // affected (old/new source and old/new destination, de-duplicated).
            $this->balances->refresh($account);
            collect([$oldAccountId, $oldDestinationId, $destinationId])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->reject(fn ($id) => $id === (int) $account->id)
                ->each(fn ($id) => $this->balances->refresh($id));

            return $transaction;
        });
    }

    public function delete(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $accountId = $transaction->account_id;
            $destId = $transaction->destination_account_id;
            $transaction->delete();

            $this->balances->refresh($accountId);
            if ($destId && (int) $destId !== (int) $accountId) {
                $this->balances->refresh($destId);
            }
        });
    }

    public static function validateCategoryType(?Category $category, string $type): bool
    {
        if ($category === null) {
            return true;
        }

        return $category->type === $type;
    }
}
