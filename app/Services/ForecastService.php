<?php

namespace App\Services;

use App\Models\RecurringTransaction;
use App\Models\SavingsGoal;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Deterministic financial forecast — the source of truth for projections.
 *
 * Projected balance = current balance
 *                     + recurring income (remaining this period)
 *                     - recurring expense (remaining this period)
 *                     - planned savings (goals commitments)
 */
class ForecastService
{
    public function __construct(
        private AccountBalanceService $balances,
        private RecurringTransactionService $recurring,
        private BillService $bills,
        private DebtService $debts,
    ) {
    }

    public function forecastForMonth(int $userId, ?CarbonImmutable $month = null): array
    {
        $month ??= CarbonImmutable::now();
        $today = CarbonImmutable::now()->startOfDay();
        $remainingStart = $today;
        $endOfMonth = $month->endOfMonth();

        $currentBalance = $this->balances->totalBalance($userId);

        $incomeCents = 0;
        $expenseCents = 0;

        if ($remainingStart->lte($endOfMonth)) {
            $recurrings = RecurringTransaction::query()
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->get();

            foreach ($recurrings as $r) {
                $occ = $this->recurring->occurrencesIn($r, $remainingStart, $endOfMonth)->count();
                if ($occ === 0) {
                    continue;
                }
                $sum = Money::toCents($r->amount) * $occ;
                if ($r->type === RecurringTransaction::TYPE_INCOME) {
                    $incomeCents += $sum;
                } else {
                    $expenseCents += $sum;
                }
            }

            // Bills & subscriptions due within the remaining period.
            $expenseCents += $this->bills->amountBetween($userId, $remainingStart, $endOfMonth)['total_cents'];

            // Scheduled debt installments within the remaining period.
            $expenseCents += $this->debts->amountBetween($userId, $remainingStart, $endOfMonth)['total_cents'];
        }

        $plannedSavingsCents = $this->plannedSavingsForMonth($userId, $month);

        $projected = Money::add($currentBalance, $incomeCents, -$expenseCents, -$plannedSavingsCents);

        return [
            'current_balance' => $currentBalance,
            'expected_income' => Money::fromCents($incomeCents),
            'expected_expenses' => Money::fromCents($expenseCents),
            'planned_savings' => Money::fromCents($plannedSavingsCents),
            'net' => Money::fromCents($incomeCents - $expenseCents - $plannedSavingsCents),
            'projected_balance' => $projected,
            'month' => $month->format('Y-m'),
        ];
    }

    /**
     * Planned savings = actual money already allocated to savings goals.
     *
     * Each goal's contributions are held in its dedicated savings account (a
     * transfer from a source account). That allocated money is real but is not
     * freely spendable, so it reduces projected balance / safe to spend.
     *
     * A goal's *required* monthly contribution is only a recommendation and is
     * NEVER counted here — only money that has actually been allocated shows
     * up. This deliberately excludes any goal whose current_amount was bumped
     * without an underlying transfer (e.g. legacy goals without an account).
     */
    public function plannedSavingsForMonth(int $userId, CarbonImmutable $month): int
    {
        $allocated = SavingsGoal::query()
            ->where('user_id', $userId)
            ->whereNotNull('account_id')
            ->with('account:id,balance')
            ->get()
            ->sum(fn (SavingsGoal $goal) => Money::toCents($goal->account?->balance ?? 0));

        return $allocated;
    }

    /**
     * Projection for a series of months starting at a given date; realised
     * from plan for future months (compounding doesn't apply to balances of
     * different accounts, so we carry a running projected balance).
     *
     * @return Collection<int, array>
     */
    public function monthlySeries(int $userId, int $months = 6, ?CarbonImmutable $from = null): Collection
    {
        $from ??= CarbonImmutable::now()->startOfMonth();
        $currentBalance = $this->balances->totalBalance($userId);

        $cursor = Money::toCents($currentBalance);
        $series = collect();

        for ($i = 0; $i < $months; $i++) {
            $month = $from->addMonthsNoOverflow($i);

            if ($i === 0) {
                $f = $this->forecastForMonth($userId, $month);
                $monthlyNet = Money::toCents($f['net']);
                $cursor = Money::toCents($f['projected_balance']);
                $series->push([
                    'month' => $month->format('Y-m'),
                    'label' => $month->translatedFormat('M y'),
                    'income' => $f['expected_income'],
                    'expenses' => $f['expected_expenses'],
                    'savings' => $f['planned_savings'],
                    'net' => $f['net'],
                    'balance' => $f['projected_balance'],
                ]);
                continue;
            }

            $summary = $this->recurring->monthlySummaryForUser($userId, $month);
            $savings = $this->plannedSavingsForMonth($userId, $month);
            $income = Money::toCents($summary['income']);

            $billMonthly = Money::toCents($this->bills->monthlyCost($userId, $month));
            $debtMonthly = Money::toCents($this->debts->summary($userId)['monthly_payments']);
            $expense = Money::toCents($summary['expense']) + $billMonthly + $debtMonthly;

            $net = $income - $expense - $savings;
            $cursor += $net;

            $series->push([
                'month' => $month->format('Y-m'),
                'label' => $month->translatedFormat('M y'),
                'income' => Money::fromCents($income),
                'expenses' => Money::fromCents($expense),
                'savings' => Money::fromCents($savings),
                'net' => Money::fromCents($net),
                'balance' => Money::fromCents($cursor),
            ]);
        }

        return $series;
    }
}
