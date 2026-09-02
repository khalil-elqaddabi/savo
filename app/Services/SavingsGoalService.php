<?php

namespace App\Services;

use App\Models\Account;
use App\Models\SavingsGoal;
use App\Support\Money;
use Carbon\CarbonImmutable;

class SavingsGoalService
{
    /**
     * Ensure the goal has a dedicated savings sub-account that holds its
     * allocated money. Contributions are transferred into this account so the
     * allocation is real (ledger-backed) while total net worth is unchanged.
     */
    public function ensureAccount(SavingsGoal $goal): Account
    {
        if ($goal->account_id) {
            return $goal->account;
        }

        $account = Account::create([
            'user_id' => $goal->user_id,
            'name' => $goal->name,
            'type' => 'savings',
            'starting_balance' => '0.00',
            'balance' => '0.00',
        ]);

        $goal->account_id = $account->id;
        $goal->saveQuietly();

        return $account;
    }

    public function status(SavingsGoal $goal, ?CarbonImmutable $reference = null): array
    {
        $reference ??= CarbonImmutable::now();

        $target = Money::toCents($goal->target_amount);
        $current = Money::toCents($goal->current_amount);
        $savedAlready = Money::fromCents($current);
        $remainingCents = max(0, $target - $current);
        $progressPercent = $target > 0 ? min(100, round(($current / $target) * 100, 1)) : 0;

        $deadline = $goal->deadline ? CarbonImmutable::parse($goal->deadline) : null;

        $daysRemaining = null;
        if ($deadline) {
            $deadlineStart = $deadline->startOfDay();
            $refStart = $reference->startOfDay();

            // Days until the deadline. A passed deadline leaves no time left.
            $daysRemaining = $refStart->gt($deadlineStart)
                ? 0
                : (int) $refStart->diffInDays($deadlineStart);
        }

        // Required saving per period to meet the deadline on time. Nothing is
        // required when the deadline is already here or in the past, because
        // the goal can no longer be reached on schedule.
        $requiredDaily = null;
        $requiredWeekly = null;
        $requiredMonthly = null;
        if ($deadline && $remainingCents > 0 && $daysRemaining !== null && $daysRemaining > 0) {
            $requiredDaily = Money::fromCents((int) ceil($remainingCents / $daysRemaining));
            $requiredWeekly = Money::fromCents((int) ceil($remainingCents / max(1, ceil($daysRemaining / 7))));
            $requiredMonthly = Money::fromCents((int) ceil($remainingCents / max(1, ceil($daysRemaining / 30))));
        }

        // Average actual monthly savings from goal history is not stored in MVP;
        // infer on-track from a nominal saving rate when no other signal exists.
        $onTrack = $deadline !== null && $remainingCents > 0
            ? false
            : ($remainingCents <= 0 ? true : null);

        $projectedCompletion = null;
        if ($remainingCents <= 0) {
            $projectedCompletion = $reference->toDateString();
        }

        return [
            'goal' => $goal,
            'saved' => $savedAlready,
            'remaining' => Money::fromCents($remainingCents),
            'progress_percent' => $progressPercent,
            'days_remaining' => $daysRemaining,
            'required_daily' => $requiredDaily,
            'required_weekly' => $requiredWeekly,
            'required_monthly' => $requiredMonthly,
            'on_track' => $onTrack,
            'projected_completion' => $projectedCompletion,
        ];
    }
}
