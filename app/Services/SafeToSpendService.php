<?php

namespace App\Services;

use App\Models\FinancialSetting;
use App\Models\RecurringTransaction;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Determines how much money is safely spendable after accounting for
 * upcoming recurring obligations, planned savings and protected money.
 *
 * safe_to_spend = current_balance
 *                 - upcoming recurring obligations
 *                 - planned savings
 *                 - protected money
 *
 * Budgets are spending plans/limits and never reserve, deduct or lock money:
 * Safe to Spend is only reduced by real transactions (e.g. an expense).
 */
class SafeToSpendService
{
    public function __construct(
        private AccountBalanceService $balances,
        private RecurringTransactionService $recurring,
        private ForecastService $forecast,
        private BillService $bills,
        private DebtService $debts,
    ) {}

    public function periodEnd(int $userId, ?CarbonImmutable $reference = null): CarbonImmutable
    {
        $reference ??= CarbonImmutable::now();
        $setting = FinancialSetting::query()->where('user_id', $userId)->first();

        if ($setting?->payday_day) {
            $day = (int) $setting->payday_day;
            $payday = $reference->startOfDay()->day($day);

            if ($payday->lt($reference->startOfDay())) {
                $payday = $payday->addMonth();
            }

            return $payday;
        }

        return $reference->endOfMonth();
    }

    public function safeToSpend(int $userId, ?CarbonImmutable $reference = null): array
    {
        $reference ??= CarbonImmutable::now();
        $setting = FinancialSetting::query()->where('user_id', $userId)->first()
            ?? new FinancialSetting(['protected_money' => 0, 'safe_to_spend_enabled' => true]);

        $currentBalance = $this->balances->totalBalance($userId);
        $periodEnd = $this->periodEnd($userId, $reference);

        $upcomingObligations = $this->upcomingObligations($userId, $reference, $periodEnd);

        $plannedSavings = $this->forecast->plannedSavingsForMonth($userId, $reference);

        $protectedMoney = $setting->safe_to_spend_enabled ? Money::toCents($setting->protected_money) : 0;

        $totalCommitments = $upcomingObligations['total_cents'] + $plannedSavings + $protectedMoney;
        $safeToSpendCents = Money::toCents($currentBalance) - $totalCommitments;

        if ($safeToSpendCents < 0) {
            $safeToSpendCents = 0;
        }

        return [
            'current_balance' => $currentBalance,
            'upcoming_obligations' => Money::fromCents($upcomingObligations['total_cents']),
            'upcoming_obligations_list' => $upcomingObligations['items'],
            'planned_savings' => Money::fromCents($plannedSavings),
            'protected_money' => Money::fromCents($protectedMoney),
            'total_commitments' => Money::fromCents($totalCommitments),
            'safe_to_spend' => Money::fromCents($safeToSpendCents),
            'period_end' => $periodEnd->toDateString(),
            'days_remaining' => max(1, (int) $reference->startOfDay()->diffInDays($periodEnd->addDays(1)->startOfDay())),
            'enabled' => (bool) $setting->safe_to_spend_enabled,
        ];
    }

    public function daily(int $userId, ?CarbonImmutable $reference = null): array
    {
        $result = $this->safeToSpend($userId, $reference);
        $daily = Money::toCents($result['safe_to_spend']) / max(1, $result['days_remaining']);

        return array_merge($result, [
            'safe_to_spend_daily' => Money::fromCents((int) floor($daily)),
        ]);
    }

    public function untilPayday(int $userId, ?CarbonImmutable $reference = null): array
    {
        $result = $this->safeToSpend($userId, $reference);
        $isPaydayPeriod = $this->periodEnd($userId, $reference);

        return array_merge($result, [
            'safe_to_spend_until_payday' => $result['safe_to_spend'],
            'payday' => $isPaydayPeriod->toDateString(),
            'is_payday_period' => true,
        ]);
    }

    private function upcomingObligations(int $userId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $from = $from->startOfDay();
        $to = $to->endOfDay();

        $recurrings = RecurringTransaction::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->where('type', RecurringTransaction::TYPE_EXPENSE)
            ->get();

        $totalCents = 0;
        $items = [];

        foreach ($recurrings as $r) {
            $occ = $this->recurring->occurrencesIn($r, $from, $to)->count();
            if ($occ === 0) {
                continue;
            }

            $sum = Money::toCents($r->amount) * $occ;
            $totalCents += $sum;
            $items[] = [
                'name' => $r->name,
                'amount' => Money::fromCents($sum),
                'occurrences' => $occ,
                'frequency' => $r->frequency,
            ];
        }

        // Bills & subscriptions due within the window.
        $bills = $this->bills->amountBetween($userId, $from, $to);
        $totalCents += $bills['total_cents'];
        foreach ($bills['items'] as $b) {
            $items[] = [
                'name' => $b['name'],
                'amount' => $b['amount'],
                'occurrences' => $b['occurrences'],
                'frequency' => $b['frequency'],
            ];
        }

        // Scheduled debt installments within the window.
        $debts = $this->debts->amountBetween($userId, $from, $to);
        $totalCents += $debts['total_cents'];
        foreach ($debts['items'] as $d) {
            $items[] = [
                'name' => $d['name'],
                'amount' => $d['amount'],
                'occurrences' => $d['occurrences'],
                'frequency' => null,
            ];
        }

        return ['total_cents' => $totalCents, 'items' => $items];
    }
}
