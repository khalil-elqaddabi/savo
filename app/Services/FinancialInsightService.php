<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\SavingsGoal;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Produces deterministic, human-readable financial insights. These are the
 * source of truth for the insights shown in the dashboard and handed to the
 * AI assistant as context — the AI never recomputes money itself.
 */
class FinancialInsightService
{
    public function __construct(
        private FinancialAnalyticsService $analytics,
        private BudgetService $budgets,
        private AccountBalanceService $balances,
        private ForecastService $forecast,
    ) {
    }

    /**
     * @return Collection<int, array{type: string, severity: string, message: string, meta?: array}>
     */
    public function forDashboard(int $userId, ?CarbonImmutable $reference = null): Collection
    {
        $reference ??= CarbonImmutable::now();
        $insights = collect();

        $compare = $this->analytics->compareMonths($userId, $reference);

        // Category spending changes
        $categoryChanges = $this->analytics->categoryComparison(
            $userId,
            $reference->startOfMonth()->toDateString(),
            $reference->endOfMonth()->toDateString(),
            $reference->subMonth()->startOfMonth()->toDateString(),
            $reference->subMonth()->endOfMonth()->toDateString(),
        );

        foreach ($categoryChanges->take(3) as $change) {
            $name = $change['name'];
            if ($change['delta_percent'] >= 15) {
                $insights->push([
                    'type' => 'category_spending',
                    'severity' => 'info',
                    'message' => __('Financial insights.category_increased', ['name' => $name, 'percent' => (int) $change['delta_percent']]),
                    'meta' => ['name' => $name, 'delta_percent' => $change['delta_percent']],
                ]);
            } elseif ($change['delta_percent'] <= -15) {
                $insights->push([
                    'type' => 'category_spending',
                    'severity' => 'positive',
                    'message' => __('Financial insights.category_decreased', ['name' => $name, 'percent' => abs((int) $change['delta_percent'])]),
                    'meta' => ['name' => $name, 'delta_percent' => $change['delta_percent']],
                ]);
            }
        }

        // Savings rate
        $savingsRate = $compare['current']['savings_rate'];
        $prevRate = $compare['previous']['savings_rate'];
        if ($savingsRate >= 0 && $savingsRate > $prevRate) {
            $insights->push([
                'type' => 'savings_rate',
                'severity' => 'positive',
                'message' => __('Financial insights.savings_rate_improved', ['rate' => $savingsRate]),
            ]);
        } elseif ($prevRate >= 0 && $savingsRate < $prevRate) {
            $insights->push([
                'type' => 'savings_rate',
                'severity' => 'warning',
                'message' => __('Financial insights.savings_rate_declined', ['rate' => $savingsRate, 'previous' => $prevRate]),
            ]);
        }

        // Budget status
        $overview = $this->budgets->currentOverview($userId, $reference);
        foreach ($overview as $status) {
            if (in_array($status['status'], ['warning', 'critical', 'exceeded'], true)) {
                $insights->push([
                    'type' => 'budget',
                    'severity' => $status['status'] === 'exceeded' ? 'danger' : ($status['status'] === 'critical' ? 'warning' : 'info'),
                    'message' => $this->budgetMessage($status['budget'], $status),
                    'meta' => ['budget_id' => $status['budget']->id, 'status' => $status['status']],
                ]);
            }
        }

        // Projected balance negative?
        $forecast = $this->forecast->forecastForMonth($userId, $reference);
        if (Money::compare($forecast['projected_balance'], 0) < 0) {
            $insights->push([
                'type' => 'projection',
                'severity' => 'danger',
                'message' => __('Financial insights.projected_negative', ['amount' => $forecast['projected_balance']]),
            ]);
        } elseif (Money::compare($forecast['projected_balance'], $forecast['current_balance']) < 0) {
            $insights->push([
                'type' => 'projection',
                'severity' => 'info',
                'message' => __('Financial insights.projected_decrease', ['amount' => $forecast['projected_balance']]),
            ]);
        }

        // Goals needing attention
        $goals = SavingsGoal::query()->where('user_id', $userId)->where('is_completed', false)->get();
        foreach ($goals as $goal) {
            if ($goal->deadline && $goal->deadline->isPast() && Money::compare($goal->current_amount, $goal->target_amount) < 0) {
                $insights->push([
                    'type' => 'goal',
                    'severity' => 'warning',
                    'message' => __('Financial insights.goal_deadline_passed', ['name' => $goal->name]),
                    'meta' => ['goal_id' => $goal->id],
                ]);
            }
        }

        return $insights->take(8)->values();
    }

    private function budgetMessage(Budget $budget, array $status): string
    {
        return match ($status['status']) {
            'exceeded' => __('Financial insights.budget_exceeded', ['name' => $budget->name, 'amount' => $status['spent']]),
            'critical' => __('Financial insights.budget_critical', ['name' => $budget->name, 'percent' => $status['percent']]),
            default => __('Financial insights.budget_warning', ['name' => $budget->name, 'percent' => $status['percent']]),
        };
    }
}
