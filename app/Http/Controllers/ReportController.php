<?php

namespace App\Http\Controllers;

use App\Services\AccountBalanceService;
use App\Services\BudgetService;
use App\Services\FinancialAnalyticsService;
use App\Services\SavingsGoalService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function __construct(
        private FinancialAnalyticsService $analytics,
        private AccountBalanceService $balances,
        private BudgetService $budgets,
        private SavingsGoalService $goalService,
    ) {
    }

    public function __invoke(Request $request)
    {
        $user = $request->user();
        $now = CarbonImmutable::now();

        [$from, $to] = $this->resolvePeriod($now, $request);

        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        $summary = $this->analytics->summary($user->id, $fromStr, $toStr);
        $byCategory = $this->analytics->byCategory($user->id, $fromStr, $toStr);
        $byAccount = $this->analytics->byAccount($user->id, $fromStr, $toStr);
        $compare = $this->analytics->compareMonths($user->id, $now);

        $prevFrom = $from->subDays($from->diffInDays($to) + 1);
        $prevTo = $from->subDay();
        $prevSummary = $this->analytics->summary($user->id, $prevFrom->toDateString(), $prevTo->toDateString());

        // Balance history across the period.
        $history = $this->balances->balanceHistory($user->id, $fromStr, $toStr);

        // Monthly comparison series for last 6 months.
        $monthly = collect(range(5, 0))->map(function ($i) use ($user, $now) {
            $m = $now->subMonthsNoOverflow($i);
            $s = $this->analytics->summary(
                $user->id,
                $m->startOfMonth()->toDateString(),
                $m->endOfMonth()->toDateString(),
            );

            return [
                'month' => $m->format('Y-m'),
                'label' => $m->translatedFormat('M y'),
                'income' => $s['income'],
                'expenses' => $s['expenses'],
            ];
        })->values();

        $budgets = $this->budgets->currentOverview($user->id)->map(fn ($s) => [
            'name' => $s['budget']->name,
            'amount' => $s['amount'],
            'spent' => $s['spent'],
            'percent' => $s['percent'],
            'status' => $s['status'],
        ])->values();

        return Inertia::render('Reports/Index', [
            'period' => ['from' => $fromStr, 'to' => $toStr, 'key' => $request->string('period')->toString()],
            'summary' => $summary,
            'previousSummary' => $prevSummary,
            'byCategory' => $byCategory,
            'byAccount' => $byAccount,
            'compare' => $compare,
            'balanceHistory' => $history,
            'monthly' => $monthly,
            'budgets' => $budgets,
            'goals' => $user->savingsGoals()->orderBy('is_completed')->get()
                ->map(fn ($g) => $this->goalService->status($g) + ['name' => $g->name])->values(),
        ]);
    }

    private function resolvePeriod(CarbonImmutable $now, Request $request): array
    {
        $key = $request->string('period')->toString();

        return match ($key) {
            'week' => [$now->startOfWeek(CarbonImmutable::MONDAY), $now->endOfWeek(CarbonImmutable::SUNDAY)],
            'prev_month' => [
                $now->subMonthNoOverflow()->startOfMonth(),
                $now->subMonthNoOverflow()->endOfMonth(),
            ],
            'year' => [$now->startOfYear(), $now->endOfYear()],
            'custom' => [
                CarbonImmutable::parse($request->string('from')->toString() ?: $now->startOfMonth()),
                CarbonImmutable::parse($request->string('to')->toString() ?: $now->endOfMonth()),
            ],
            default => [$now->startOfMonth(), $now->endOfMonth()],
        };
    }
}
