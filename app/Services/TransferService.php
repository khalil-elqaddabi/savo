<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Handles transfers between two accounts.
 *
 * A transfer NEVER counts as income or expense in analytics. It only moves
 * money between the user's own accounts. Transferring to the same account is
 * rejected.
 */
class TransferService
{
    public function __construct(private AccountBalanceService $balances)
    {
    }

    public function create(Account $source, Account $destination, array $data): Transaction
    {
        if ((int) $source->id === (int) $destination->id) {
            throw new InvalidArgumentException('Cannot transfer to the same account.');
        }

        if ((int) $source->user_id !== (int) $destination->user_id) {
            throw new InvalidArgumentException('Cannot transfer between accounts of different users.');
        }

        $amount = Money::fromCents(Money::toCents($data['amount']));
        if (Money::compare($amount, 0) < 0) {
            $amount = Money::fromCents(-Money::toCents($amount));
        }

        return DB::transaction(function () use ($source, $destination, $data, $amount) {
            $transaction = Transaction::create([
                'user_id' => $source->user_id,
                'account_id' => $source->id,
                'destination_account_id' => $destination->id,
                'type' => Transaction::TYPE_TRANSFER,
                'amount' => $amount,
                'date' => $data['date'] ?? now()->toDateString(),
                'description' => $data['description'] ?? null,
                'is_transfer' => true,
            ]);

            $this->balances->refresh($source);
            $this->balances->refresh($destination);

            return $transaction;
        });
    }

    public function delete(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $sourceId = $transaction->account_id;
            $destId = $transaction->destination_account_id;
            $transaction->delete();

            $this->balances->refresh($sourceId);
            if ($destId && (int) $destId !== (int) $sourceId) {
                $this->balances->refresh($destId);
            }
        });
    }
}
