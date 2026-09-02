<?php

namespace App\Services;

use App\Models\Bill;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Support\Collection;

/**
 * Deterministic engine for bills & subscriptions.
 *
 * Mirrors RecurringTransactionService's occurrence logic so bills behave
 * identically (phase-preserving day-of-month / day-of-week handling). Bills
 * are a separate, purpose-built concept from recurring transactions (a bill is
 * an obligation with a status and a linked ledger account), and are folded into
 * Safe to Spend / Forecast / Health / Notifications / Assistant via the
 * obligation aggregator rather than duplicating recurring logic.
 */
class BillService
{
    public function occurrencesIn(Bill $bill, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        if ($bill->status !== Bill::STATUS_ACTIVE) {
            return collect();
        }

        $start = $bill->start_date ? CarbonImmutable::parse($bill->start_date) : CarbonImmutable::parse($bill->next_payment_date);
        $next = $bill->next_payment_date ? CarbonImmutable::parse($bill->next_payment_date) : $start;
        $end = $bill->end_date ? CarbonImmutable::parse($bill->end_date) : null;

        $interval = $this->interval($bill->frequency, (int) $bill->interval);

        $cursor = $next->max($start)->startOfDay();
        $guard = 0;
        while ($cursor->lt($from) && $guard < 2000) {
            $cursor = $this->advance($cursor, $interval, $bill->frequency);
            $guard++;
        }

        $occurrences = collect();
        $guard = 0;
        while ($cursor->lte($to) && $guard < 2000) {
            if ($end && $cursor->gt($end)) {
                break;
            }
            $occurrences->push($cursor);
            $cursor = $this->advance($cursor, $interval, $bill->frequency);
            $guard++;
        }

        return $occurrences;
    }

    public function monthlyAmount(Bill $bill): string
    {
        $cents = Money::toCents($bill->amount);
        $perMonth = match ($bill->frequency) {
            Bill::FREQ_DAILY => $cents * 30 * (int) max(1, $bill->interval),
            Bill::FREQ_WEEKLY => $cents * 52 / 12,
            Bill::FREQ_MONTHLY => $cents * (int) max(1, $bill->interval),
            Bill::FREQ_YEARLY => $cents / 12,
            default => $cents,
        };

        return Money::fromCents((int) round($perMonth, 0, PHP_ROUND_HALF_UP));
    }

    /**
     * Monthly recurring cost of active bills (used by the bills page, health
     * score and forecast).
     */
    public function monthlyCost(int $userId, ?CarbonImmutable $reference = null): string
    {
        $bills = Bill::query()->where('user_id', $userId)->where('status', Bill::STATUS_ACTIVE)->get();

        $total = $bills->sum(fn (Bill $b) => Money::toCents($this->monthlyAmount($b)));

        return Money::fromCents((int) $total);
    }

    public function yearlyCost(int $userId): string
    {
        return Money::mul($this->monthlyCost($userId), 12);
    }

    /**
     * Upcoming bill occurrences for the dashboard / notifications.
     *
     * @return Collection<int, array>
     */
    public function upcoming(int $userId, int $limit = 8, ?CarbonImmutable $from = null): Collection
    {
        $from ??= CarbonImmutable::now()->startOfDay();
        $to = $from->copy()->addDays(60);

        $bills = Bill::query()
            ->where('user_id', $userId)
            ->where('status', Bill::STATUS_ACTIVE)
            ->with('category', 'account')
            ->get();

        $events = [];
        foreach ($bills as $bill) {
            foreach ($this->occurrencesIn($bill, $from, $to) as $date) {
                $events[] = [
                    'date' => $date->toDateString(),
                    'date_human' => $date->translatedFormat('D j M'),
                    'bill' => [
                        'id' => $bill->id,
                        'name' => $bill->name,
                        'amount' => $bill->amount,
                        'currency' => $bill->currency,
                        'frequency' => $bill->frequency,
                        'category' => $bill->category?->name,
                        'category_icon' => $bill->category?->icon,
                    ],
                ];
            }
        }

        usort($events, fn ($a, $b) => $a['date'] <=> $b['date']);

        return collect(array_slice($events, 0, $limit))->values();
    }

    /**
     * Sum of active bills with a payment due within the window.
     */
    public function amountBetween(int $userId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $bills = Bill::query()->where('user_id', $userId)->where('status', Bill::STATUS_ACTIVE)->get();

        $totalCents = 0;
        $items = [];
        foreach ($bills as $bill) {
            $occ = $this->occurrencesIn($bill, $from, $to)->count();
            if ($occ === 0) {
                continue;
            }
            $sum = Money::toCents($bill->amount) * $occ;
            $totalCents += $sum;
            $items[] = [
                'id' => $bill->id,
                'name' => $bill->name,
                'amount' => Money::fromCents($sum),
                'occurrences' => $occ,
                'frequency' => $bill->frequency,
            ];
        }

        return ['total_cents' => $totalCents, 'items' => $items];
    }

    private function interval(string $frequency, int $interval): CarbonInterval
    {
        return match ($frequency) {
            Bill::FREQ_DAILY => CarbonInterval::days($interval),
            Bill::FREQ_WEEKLY => CarbonInterval::weeks($interval),
            Bill::FREQ_YEARLY => CarbonInterval::years($interval),
            default => CarbonInterval::months($interval),
        };
    }

    private function advance(CarbonImmutable $date, CarbonInterval $interval, string $frequency): CarbonImmutable
    {
        if ($frequency === Bill::FREQ_MONTHLY) {
            return $date->addMonthsNoOverflow((int) $interval->months);
        }

        return $date->add($interval);
    }
}
