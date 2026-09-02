<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\SavingsGoal;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Deterministic, transparent Financial Health Score (0 - 100).
 *
 * The score is computed only from real application data and never from an
 * external AI. Every factor contributes a finite number of points; the
 * breakdown reports which factors helped and which held the score back, so the
 * number is explainable and changes deterministically as financial data
 * changes.
 *
 * Scoring model (weighted, out of 100):
 *   - savings rate            up to 30 pts
 *   - budget adherence        up to 20 pts
 *   - obligation burden       up to 15 pts
 *   - savings goal progress   up to 15 pts
 *   - debt load               up to 10 pts
 *   - cash position           up to 10 pts
 */
class HealthScoreService
{
    public function __construct(
        private FinancialAnalyticsService $analytics,
        private BudgetService $budgets,
        private AccountBalanceService $balances,
        private BillService $bills,
        private DebtService $debts,
        private RecurringTransactionService $recurring,
    ) {
    }

    public function score(int $userId, ?CarbonImmutable $reference = null): array
    {
        $reference ??= CarbonImmutable::now();

        $factors = [];
        $score = 0;
        $positive = [];
        $negative = [];

        // ---- savings rate (30) ----
        $summary = $this->analytics->summary(
            $userId,
            $reference->subDays(30)->toDateString(),
            $reference->toDateString()
        );
        $incomeCents = Money::toCents($summary['income']);
        $expenseCents = Money::toCents($summary['expenses']);
        $savingsRate = $incomeCents > 0
            ? (($incomeCents - $expenseCents) / $incomeCents) * 100
            : ($expenseCents > 0 ? -100 : 0);

        $savingsPts = round(min(30, max(0, 30 * (0.5 + ($savingsRate / 100) * 0.5))));
        $score += (int) $savingsPts;
        $factors['savings_rate'] = ['points' => (int) $savingsPts, 'max' => 30, 'value' => round($savingsRate, 1)];
        if ($savingsRate >= 15) {
            $positive[] = __('health.good_savings_rate', ['rate' => round($savingsRate)]);
        } elseif ($savingsRate < 0) {
            $negative[] = __('health.negative_savings_rate');
        }

        // ---- budget adherence (20) ----
        $overview = $this->budgets->currentOverview($userId, $reference);
        if ($overview->isEmpty()) {
            $score += 14;
            $factors['budget_adherence'] = ['points' => 14, 'max' => 20, 'value' => null];
            $positive[] = __('health.no_budgets_ok');
        } else {
            $avgPercent = $overview->avg('raw_percent') * 100;
            $budgetPts = round(20 * max(0, min(1, 1 - ($avgPercent / 100))));
            $score += (int) $budgetPts;
            $factors['budget_adherence'] = ['points' => (int) $budgetPts, 'max' => 20, 'value' => round($avgPercent, 1)];
            $overUsed = $overview->filter(fn ($b) => $b['status'] === 'exceeded')->count();
            if ($overUsed > 0) {
                $negative[] = __('health.budgets_exceeded', ['count' => $overUsed]);
            } else {
                $positive[] = __('health.spending_below_budget');
            }
        }

        // ---- obligation burden (15) ----
        $monthlyIncome = Money::toCents(
            $this->recurring->monthlySummaryForUser($userId, $reference->startOfMonth())['income']
        );
        $recurringExpense = Money::toCents(
            $this->recurring->monthlySummaryForUser($userId, $reference->startOfMonth())['expense']
        );
        $billCost = Money::toCents($this->bills->monthlyCost($userId, $reference));
        $debtPayment = $this->debts->summary($userId)['monthly_payments'];
        $debtCents = Money::toCents($debtPayment);

        $obligationCents = $recurringExpense + $billCost + $debtCents;
        $obligationRate = $monthlyIncome > 0 ? ($obligationCents / $monthlyIncome) * 100 : 100;
        $obligationPts = round(15 * (1 - min(1, $obligationRate / 100)));
        $score += (int) $obligationPts;
        $factors['obligation_burden'] = ['points' => (int) $obligationPts, 'max' => 15, 'value' => round($obligationRate, 1)];
        if ($obligationRate > 70) {
            $negative[] = __('health.high_recurring_commitments');
        } elseif ($obligationRate <= 30 && $obligationRate >= 0) {
            $positive[] = __('health.low_obligations');
        }

        // ---- savings goal progress (15) ----
        $goals = SavingsGoal::query()
            ->where('user_id', $userId)
            ->where('is_completed', false)
            ->get(['target_amount', 'current_amount', 'deadline']);
        if ($goals->isEmpty()) {
            $score += 8;
            $factors['savings_goals'] = ['points' => 8, 'max' => 15, 'value' => null];
            $negative[] = __('health.no_savings_goals');
        } else {
            $progresses = $goals->map(function (SavingsGoal $g) {
                $t = Money::toCents($g->target_amount);
                return $t > 0 ? (Money::toCents($g->current_amount) / $t) * 100 : 0;
            });
            $avgProgress = $progresses->avg();
            $goalPts = round(15 * ($avgProgress / 100));
            $score += (int) $goalPts;
            $factors['savings_goals'] = ['points' => (int) $goalPts, 'max' => 15, 'value' => round($avgProgress, 1)];
            if ($avgProgress >= 70) {
                $positive[] = __('health.good_goal_progress', ['percent' => round($avgProgress)]);
            } else {
                $negative[] = __('health.goals_in_progress');
            }
        }

        // ---- debt load (10) ----
        $debtSummary = $this->debts->summary($userId);
        $debtRemaining = Money::toCents($debtSummary['total_remaining']);
        $debtPts = $debtRemaining > 0 ? round(10 * max(0, min(1, 1 - ($debtRemaining / max(1, $incomeCents * 12))))) : 10;
        $score += (int) $debtPts;
        $factors['debt_load'] = ['points' => (int) $debtPts, 'max' => 10, 'value' => (float) $debtSummary['total_remaining']];
        if ($debtRemaining <= 0) {
            $positive[] = __('health.no_debt');
        } else {
            $negative[] = __('health.has_debt', ['amount' => $debtSummary['total_remaining']]);
        }

        // ---- cash position (10) ----
        $balanceCents = Money::toCents($this->balances->totalBalance($userId));
        $cashPts = $balanceCents > 0 ? 10 : ($balanceCents === 0 ? 5 : 0);
        $score += $cashPts;
        $factors['cash_position'] = ['points' => $cashPts, 'max' => 10, 'value' => (float) $this->balances->totalBalance($userId)];
        if ($balanceCents < 0) {
            $negative[] = __('health.negative_balance');
        }

        $score = max(0, min(100, $score));

        return [
            'score' => (int) $score,
            'factors' => $factors,
            'positive' => collect($positive)->unique()->values()->take(5)->all(),
            'negative' => collect($negative)->unique()->values()->take(5)->all(),
            'generated_at' => $reference->toDateTimeString(),
        ];
    }
}
