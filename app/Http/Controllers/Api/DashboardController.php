<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Services\AccountBalanceService;
use App\Services\BudgetService;
use App\Services\FinancialAnalyticsService;
use App\Services\FinancialInsightService;
use App\Services\ForecastService;
use App\Services\RecurringTransactionService;
use App\Services\SafeToSpendService;
use App\Services\SavingsGoalService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private AccountBalanceService $balances,
        private SafeToSpendService $safeToSpend,
        private FinancialAnalyticsService $analytics,
        private BudgetService $budgets,
        private SavingsGoalService $goalService,
        private ForecastService $forecast,
        private RecurringTransactionService $recurring,
        private FinancialInsightService $insights,
    ) {
    }

    public function __invoke(Request $request)
    {
        $user = $request->user();
        $now = CarbonImmutable::now();
        $monthStart = $now->startOfMonth()->toDateString();
        $monthEnd = $now->endOfMonth()->toDateString();
        $prevStart = $now->subMonth()->startOfMonth()->toDateString();
        $prevEnd = $now->subMonth()->endOfMonth()->toDateString();

        $summary = $this->analytics->summary($user->id, $monthStart, $monthEnd);
        $previous = $this->analytics->summary($user->id, $prevStart, $prevEnd);
        $safe = $this->safeToSpend->daily($user->id);
        $budgetOverview = $this->budgets->currentOverview($user->id);
        $goals = SavingsGoal::query()->where('user_id', $user->id)->orderBy('is_completed')->orderBy('deadline')->limit(5)->get();
        $goalStatuses = $goals->map(fn ($g) => $this->goalService->status($g));

        $inOut = $this->analytics->byCategory($user->id, $monthStart, $monthEnd);
        $recent = Transaction::query()
            ->where('user_id', $user->id)
            ->with(['account', 'destinationAccount', 'category'])
            ->latest('date')
            ->latest('id')
            ->limit(8)
            ->get();

        $accounts = $user->activeAccounts()->orderBy('balance', 'desc')->get();

        return response()->json([
            'data' => [
                'total_balance' => $this->balances->totalBalance($user->id),
                'accounts' => $accounts->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'type' => $a->type,
                    'balance' => $a->balance,
                    'icon' => $a->icon,
                    'color' => $a->color,
                ])->values(),
                'safe_to_spend' => $safe,
                'monthly' => [
                    'income' => $summary['income'],
                    'expenses' => $summary['expenses'],
                    'net' => $summary['net'],
                    'prev_income' => $previous['income'],
                    'prev_expenses' => $previous['expenses'],
                    'income_delta' => $this->delta((float) $previous['income'], (float) $summary['income']),
                    'expense_delta' => $this->delta((float) $previous['expenses'], (float) $summary['expenses']),
                ],
                'spending_by_category' => $inOut,
                'budgets' => $budgetOverview->map(fn ($s) => [
                    'id' => $s['budget']->id,
                    'name' => $s['budget']->name,
                    'scope' => $s['budget']->scope,
                    'period' => $s['budget']->period,
                    'category' => $s['budget']->category?->name,
                    'amount' => $s['amount'],
                    'spent' => $s['spent'],
                    'remaining' => $s['remaining'],
                    'percent' => $s['percent'],
                    'status' => $s['status'],
                ])->values(),
                'goals' => $goalStatuses->map(fn ($s) => $s + [
                    'id' => $s['goal']->id,
                    'name' => $s['goal']->name,
                    'target' => $s['goal']->target_amount,
                    'icon' => $s['goal']->icon,
                    'color' => $s['goal']->color,
                    'deadline' => $s['goal']->deadline?->toDateString(),
                ])->values(),
                'forecast' => $this->forecast->forecastForMonth($user->id),
                'forecast_series' => $this->forecast->monthlySeries($user->id, 6),
                'upcoming' => $this->recurring->upcomingForUser($user->id, 6),
                'recent_transactions' => $recent->map(fn ($t) => [
                    'id' => $t->id,
                    'type' => $t->type,
                    'amount' => $t->amount,
                    'date' => $t->date->toDateString(),
                    'description' => $t->description,
                    'account' => $t->account?->name,
                    'destination' => $t->destinationAccount?->name,
                    'category' => $t->category?->name,
                    'category_icon' => $t->category?->icon,
                    'category_color' => $t->category?->color,
                ])->values(),
                'insights' => $this->insights->forDashboard($user->id),
            ],
        ]);
    }

    private function delta(float $previous, float $current): ?float
    {
        if ($previous == 0) {
            return $current == 0 ? 0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}