<?php

namespace App\Services;

use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Support\Collection;

class RecurringTransactionService
{
    /**
     * List occurrence dates for a recurring transaction within [from, to],
     * starting from max(start_date, next_occurrence) and bounded by end_date.
     *
     * @return Collection<int, CarbonImmutable>
     */
    public function occurrencesIn(RecurringTransaction $recurring, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        if (! $recurring->is_active) {
            return collect();
        }

        $start = CarbonImmutable::parse($recurring->start_date);
        $next = $recurring->next_occurrence ? CarbonImmutable::parse($recurring->next_occurrence) : $start;
        $end = $recurring->end_date ? CarbonImmutable::parse($recurring->end_date) : null;

        $interval = $this->interval($recurring->frequency, (int) $recurring->interval);

        // Anchor on the nominal occurrence (next_occurrence or start_date) so
        // the configured phase (day-of-month / day-of-week) is preserved, then
        // walk forward in whole intervals until the first occurrence inside the
        // requested window. Directly jumping to $from would reset the phase.
        $cursor = $next->max($start)->startOfDay();
        $guard = 0;
        while ($cursor->lt($from) && $guard < 2000) {
            $cursor = $this->advance($cursor, $interval, $recurring->frequency);
            $guard++;
        }

        $occurrences = collect();
        $guard = 0;
        while ($cursor->lte($to) && $guard < 2000) {
            if ($end && $cursor->gt($end)) {
                break;
            }

            $occurrences->push($cursor);
            $cursor = $this->advance($cursor, $interval, $recurring->frequency);
            $guard++;
        }

        return $occurrences;
    }

    /**
     * Amount per month contributed by a recurring transaction (used by the
     * forecast engine for averaging).
     */
    public function monthlyAmount(RecurringTransaction $recurring): string
    {
        $amountCents = Money::toCents($recurring->amount);
        $perMonth = match ($recurring->frequency) {
            RecurringTransaction::FREQ_DAILY => $amountCents * 30 * (int) max(1, $recurring->interval),
            RecurringTransaction::FREQ_WEEKLY => $amountCents * 52 / 12,
            RecurringTransaction::FREQ_MONTHLY => $amountCents * (int) max(1, $recurring->interval),
            RecurringTransaction::FREQ_YEARLY => $amountCents / 12,
            default => $amountCents,
        };

        return Money::fromCents((int) round($perMonth, 0, PHP_ROUND_HALF_UP));
    }

    /**
     * Aggregate recurring income and expense expected for a single month.
     *
     * @return array{income: string, expense: string}
     */
    public function monthlySummaryForUser(int $userId, CarbonImmutable $month): array
    {
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();

        $recurrings = RecurringTransaction::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        $incomeCents = 0;
        $expenseCents = 0;

        foreach ($recurrings as $r) {
            $occurrences = $this->occurrencesIn($r, $start, $end)->count();
            if ($occurrences === 0) {
                continue;
            }

            $sum = Money::toCents($r->amount) * $occurrences;
            if ($r->type === Transaction::TYPE_INCOME) {
                $incomeCents += $sum;
            } else {
                $expenseCents += $sum;
            }
        }

        return [
            'income' => Money::fromCents($incomeCents),
            'expense' => Money::fromCents($expenseCents),
        ];
    }

    /**
     * Upcoming occurrences for the dashboard (across all active recurring).
     *
     * @return Collection<int, array>
     */
    public function upcomingForUser(int $userId, int $limit = 8): Collection
    {
        $from = CarbonImmutable::now()->startOfDay();
        $to = $from->addDays(60);

        $recurrings = RecurringTransaction::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->with('account', 'category')
            ->get();

        $events = [];
        foreach ($recurrings as $r) {
            foreach ($this->occurrencesIn($r, $from, $to) as $date) {
                $events[] = [
                    'date' => $date->toDateString(),
                    'date_human' => $date->translatedFormat('D j M'),
                    'recurring' => [
                        'id' => $r->id,
                        'name' => $r->name,
                        'type' => $r->type,
                        'amount' => $r->amount,
                        'frequency' => $r->frequency,
                        'account' => $r->account?->name,
                        'category' => $r->category?->name,
                    ],
                ];
            }
        }

        usort($events, fn ($a, $b) => $a['date'] <=> $b['date']);

        return collect(array_slice($events, 0, $limit))->values();
    }

    private function interval(string $frequency, int $interval): CarbonInterval
    {
        return match ($frequency) {
            RecurringTransaction::FREQ_DAILY => CarbonInterval::days($interval),
            RecurringTransaction::FREQ_WEEKLY => CarbonInterval::weeks($interval),
            RecurringTransaction::FREQ_YEARLY => CarbonInterval::years($interval),
            default => CarbonInterval::months($interval),
        };
    }

    private function advance(CarbonImmutable $date, CarbonInterval $interval, string $frequency): CarbonImmutable
    {
        if ($frequency === RecurringTransaction::FREQ_MONTHLY) {
            return $date->addMonthsNoOverflow((int) $interval->months);
        }

        return $date->add($interval);
    }
}
