<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Transaction;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Computes budget performance (spent, remaining, percentage, status) for
 * weekly and monthly budgets, both overall and per-category.
 *
 * Transfers are excluded because they are neither income nor expense.
 */
class BudgetService
{
    public function periodWindow(Budget $budget, ?CarbonImmutable $reference = null): array
    {
        $reference ??= CarbonImmutable::now();

        if ($budget->period === Budget::PERIOD_WEEKLY) {
            $start = $reference->startOfWeek(CarbonImmutable::MONDAY);
            $end = $start->endOfDay()->addDays(6)->endOfDay();
        } else {
            $start = $reference->startOfMonth();
            $end = $reference->endOfMonth();
        }

        return [$start, $end];
    }

    /**
     * Sum expenses for the current period matching a budget's scope.
     *
     * For a budget shared with other users, spending is aggregated across the
     * owner and every member. For a personal budget this remains identical to
     * the original owner-only behaviour.
     */
    public function spentForPeriod(Budget $budget, ?CarbonImmutable $reference = null): string
    {
        [$start, $end] = $this->periodWindow($budget, $reference);

        $userIds = $budget->spendingUserIds();

        $query = Transaction::query()
            ->whereIn('user_id', $userIds)
            ->where('type', Transaction::TYPE_EXPENSE)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()]);

        if ($budget->scope === Budget::SCOPE_CATEGORY && $budget->category_id) {
            $query->where('category_id', $budget->category_id);
        }

        return $query->sum('amount') ?: '0.00';
    }

    /**
     * @param  CarbonImmutable|null  $reference
     */
    public function status(Budget $budget, ?CarbonImmutable $reference = null): array
    {
        $amountCents = Money::toCents($budget->amount);
        $spent = $this->spentForPeriod($budget, $reference);
        $spentCents = Money::toCents($spent);
        $remainingCents = $amountCents - $spentCents;
        $percent = $amountCents > 0 ? (int) round(($spentCents / $amountCents) * 100) : 0;

        $status = 'healthy';
        if ($percent >= 100) {
            $status = 'exceeded';
        } elseif ($percent >= 95) {
            $status = 'critical';
        } elseif ($percent >= 80) {
            $status = 'warning';
        }

        [$start, $end] = $this->periodWindow($budget, $reference);

        return [
            'budget' => $budget,
            'amount' => $budget->amount,
            'spent' => Money::fromCents($spentCents),
            'remaining' => Money::fromCents($remainingCents),
            'percent' => min(999, $percent),
            'raw_percent' => $amountCents > 0 ? $spentCents / $amountCents : 0,
            'status' => $status,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
        ];
    }

    /**
     * Aggregate budgets grouped by scope/period for the current period.
     *
     * Returns budgets the user owns, plus budgets shared with them, enriched
     * with membership info so the UI can distinguish owned vs shared budgets.
     *
     * @return Collection<int, array>
     */
    public function currentOverview(int $userId, ?CarbonImmutable $reference = null): Collection
    {
        $budgetIds = Budget::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->pluck('id');

        $sharedIds = Budget::query()
            ->where('is_active', true)
            ->whereHas('members', fn ($q) => $q->where('user_id', $userId))
            ->pluck('id');

        $budgets = Budget::query()
            ->whereIn('id', $budgetIds->concat($sharedIds)->unique())
            ->with(['category', 'members.user'])
            ->get();

        return $budgets->map(function (Budget $b) use ($reference, $userId) {
            $overview = $this->status($b, $reference);

            $overview['is_owner'] = (int) $b->user_id === (int) $userId;
            $overview['role'] = $overview['is_owner']
                ? 'owner'
                : ($b->members->firstWhere('user_id', $userId)?->role ?? 'viewer');
            $overview['members'] = $b->members->map(fn ($m) => [
                'id' => $m->user_id,
                'name' => $m->user?->name,
                'email' => $m->user?->email,
                'role' => $m->role,
            ])->values()->all();

            return $overview;
        })->values();
    }
}
