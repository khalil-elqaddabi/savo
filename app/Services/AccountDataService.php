<?php

namespace App\Services;

use App\Models\BudgetShare;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Destructive account-data operations that only ever touch a single user's
 * rows while preserving the account itself (and shared/system categories).
 */
class AccountDataService
{
    /**
     * Wipe all of a single user's financial and app data, returning them to a
     * clean, new-account state. The User row is preserved. Shared system
     * categories (user_id = NULL) are never touched.
     */
    public function reset(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Polymorphic tables have no FK constraint to the user, so clean
            // them explicitly first.
            $user->notifications()->delete();
            $user->tokens()->delete();

            // Department of the user's sharing relationships: their memberships
            // in budgets owned by other users must be removed too. Shares owned
            // by the user are removed by the cascade when their budgets are
            // deleted below, but deleting by user_id here is explicit and safe.
            BudgetShare::query()
                ->where('user_id', $user->id)
                ->delete();

            // Domain data. Transactions are removed before accounts so that
            // cross-account rows (e.g. destination_account_id) resolve cleanly.
            $user->recurringTransactions()->delete();
            $user->budgets()->delete();
            $user->savingsGoals()->delete();
            $user->bills()->delete();
            $user->debts()->delete();
            $user->transactions()->delete();
            $user->accounts()->delete();
            $user->aiConversations()->delete();
            $user->categories()->delete();

            $user->financialSetting()?->delete();
        });

        // Restore defaults so the account returns to a clean, new state.
        $user->getFinancialSetting();
    }
}
