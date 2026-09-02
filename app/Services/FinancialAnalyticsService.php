<?php

namespace App\Services;

use App\Models\Transaction;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Deterministic analytics. Only income and expense transactions are counted;
 * transfers are excluded so they never distort income/expense reports.
 */
class FinancialAnalyticsService
{
    public function summary(int $userId, string $from, string $to): array
    {
        $rows = Transaction::query()
            ->where('user_id', $userId)
            ->where('type', '!=', Transaction::TYPE_TRANSFER)
            ->whereBetween('date', [$from, $to])
            ->get(['type', 'amount', 'category_id', 'date']);

        $incomeCents = 0;
        $expenseCents = 0;
        $count = 0;
        $categories = [];

        foreach ($rows as $row) {
            $count++;
            if ($row->type === Transaction::TYPE_INCOME) {
                $incomeCents += Money::toCents($row->amount);
            } else {
                $expenseCents += Money::toCents($row->amount);
                $categories[(int) $row->category_id] = ($categories[(int) $row->category_id] ?? 0) + Money::toCents($row->amount);
            }
        }

        $days = max(1, (int) CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) + 1);
        $avgDailySpend = Money::fromCents($days > 0 ? (int) round($expenseCents / $days) : 0);
        $savingsRate = $incomeCents > 0
            ? round((($incomeCents - $expenseCents) / $incomeCents) * 100, 1)
            : 0;

        return [
            'income' => Money::fromCents($incomeCents),
            'expenses' => Money::fromCents($expenseCents),
            'net' => Money::fromCents($incomeCents - $expenseCents),
            'transaction_count' => $count,
            'avg_daily_spend' => $avgDailySpend,
            'savings_rate' => $savingsRate,
            'days' => $days,
            'category_spend' => $categories,
        ];
    }

    public function byCategory(int $userId, string $from, string $to): Collection
    {
        $rows = Transaction::query()
            ->where('user_id', $userId)
            ->where('type', Transaction::TYPE_EXPENSE)
            ->whereBetween('date', [$from, $to])
            ->with('category:id,name,icon,color')
            ->get(['category_id', 'amount']);

        $agg = [];
        foreach ($rows as $row) {
            $key = (int) $row->category_id;
            $agg[$key]['cents'] = ($agg[$key]['cents'] ?? 0) + Money::toCents($row->amount);
            $agg[$key]['category'] = $row->category;
        }

        $total = 0;
        foreach ($agg as $key => $a) {
            $total += $a['cents'];
        }

        return collect($agg)
            ->map(fn ($a, $key) => [
                'category_id' => $key,
                'name' => $a['category']?->name ?? __('Uncategorized'),
                'icon' => $a['category']?->icon,
                'color' => $a['category']?->color,
                'amount' => Money::fromCents($a['cents']),
                'share' => $total > 0 ? round(($a['cents'] / $total) * 100, 1) : 0,
            ])
            ->sortByDesc('amount')
            ->values();
    }

    public function byAccount(int $userId, string $from, string $to): Collection
    {
        $rows = Transaction::query()
            ->where('user_id', $userId)
            ->where('type', Transaction::TYPE_EXPENSE)
            ->whereBetween('date', [$from, $to])
            ->with('account:id,name')
            ->get(['account_id', 'amount']);

        $agg = [];
        foreach ($rows as $row) {
            $key = (int) $row->account_id;
            $agg[$key]['cents'] = ($agg[$key]['cents'] ?? 0) + Money::toCents($row->amount);
            $agg[$key]['account'] = $row->account;
        }

        return collect($agg)
            ->map(fn ($a, $key) => [
                'account_id' => $key,
                'name' => $a['account']?->name ?? __('Deleted account'),
                'amount' => Money::fromCents($a['cents']),
            ])
            ->sortByDesc('amount')
            ->values();
    }

    public function compareMonths(int $userId, CarbonImmutable $currentMonth): array
    {
        $current = $this->summary(
            $userId,
            $currentMonth->startOfMonth()->toDateString(),
            $currentMonth->endOfMonth()->toDateString()
        );
        $previous = $this->summary(
            $userId,
            $currentMonth->subMonth()->startOfMonth()->toDateString(),
            $currentMonth->subMonth()->endOfMonth()->toDateString()
        );

        $incomeDelta = $this->percentChange((float) $previous['income'], (float) $current['income']);
        $expenseDelta = $this->percentChange((float) $previous['expenses'], (float) $current['expenses']);

        return [
            'current' => $current,
            'previous' => $previous,
            'income_delta_percent' => $incomeDelta,
            'expense_delta_percent' => $expenseDelta,
            'expense_absolute_delta' => Money::sub($current['expenses'], $previous['expenses']),
        ];
    }

    public function categoryComparison(int $userId, string $currentFrom, string $currentTo, string $previousFrom, string $previousTo): Collection
    {
        $current = $this->byCategory($userId, $currentFrom, $currentTo)->keyBy('category_id');
        $previous = $this->byCategory($userId, $previousFrom, $previousTo)->keyBy('category_id');

        $ids = $current->keys()->merge($previous->keys())->unique();

        return $ids->map(function ($id) use ($current, $previous) {
            $c = $current->get($id);
            $p = $previous->get($id);

            return [
                'category_id' => $id,
                'name' => $c['name'] ?? $p['name'],
                'icon' => $c['icon'] ?? $p['icon'],
                'color' => $c['color'] ?? $p['color'],
                'current' => $c['amount'] ?? '0.00',
                'previous' => $p['amount'] ?? '0.00',
                'delta_percent' => $this->percentChange((float) ($p['amount'] ?? 0), (float) ($c['amount'] ?? 0)),
            ];
        })->sortByDesc('current')->values();
    }

    private function percentChange(float $previous, float $current): float
    {
        if ($previous == 0) {
            return $current == 0 ? 0 : 100.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
