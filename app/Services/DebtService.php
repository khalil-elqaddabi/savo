<?php

namespace App\Services;

use App\Models\Debt;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Deterministic debt & loans tracker.
 *
 * Debt reduces available money (Safe to Spend, Forecast and Health Score) via
 * scheduled installments. "Owed to user" debts are receivables and do not
 * reduce spendable money, but they are still tracked and reported.
 */
class DebtService
{
    public function monthlyPaymentAmount(Debt $debt): string
    {
        $custom = Money::toCents($debt->installment_amount);

        if ($custom > 0) {
            return $debt->installment_amount;
        }

        $remaining = Money::toCents($debt->remaining_amount);
        $perMonth = match ($debt->frequency) {
            Debt::FREQ_WEEKLY => $remaining / (52 / 12),
            Debt::FREQ_YEARLY => $remaining / 12,
            default => $remaining,
        };

        return Money::fromCents((int) round($perMonth, 0, PHP_ROUND_HALF_UP));
    }

    /**
     * @return Collection<int, array>
     */
    public function all(int $userId, bool $includePaidOff = false): Collection
    {
        $query = Debt::query()
            ->where('user_id', $userId)
            ->with('account');

        if (! $includePaidOff) {
            $query->where('status', '!=', Debt::STATUS_PAID_OFF);
        }

        return $query->orderBy('status')->orderBy('next_payment_date')->get()
            ->map(fn (Debt $d) => $this->toArray($d))
            ->values();
    }

    public function toArray(Debt $debt): array
    {
        return [
            'id' => $debt->id,
            'name' => $debt->name,
            'type' => $debt->type,
            'original_amount' => $debt->original_amount,
            'remaining_amount' => $debt->remaining_amount,
            'interest_rate' => $debt->interest_rate,
            'installment_amount' => $debt->installment_amount,
            'frequency' => $debt->frequency,
            'next_payment_date' => $debt->next_payment_date?->toDateString(),
            'due_date' => $debt->due_date?->toDateString(),
            'notes' => $debt->notes,
            'status' => $debt->status,
            'account' => $debt->account?->name,
            'monthly_payment' => $this->monthlyPaymentAmount($debt),
            'progress' => $this->progress($debt),
            'payments_remaining' => $this->paymentsRemaining($debt),
        ];
    }

    public function summary(int $userId): array
    {
        $debts = Debt::query()
            ->where('user_id', $userId)
            ->where('status', '!=', Debt::STATUS_PAID_OFF)
            ->get();

        $owedByUser = $debts->filter(fn (Debt $d) => $d->type !== Debt::TYPE_OWED_TO_USER);
        $owedToUser = $debts->filter(fn (Debt $d) => $d->type === Debt::TYPE_OWED_TO_USER);

        $totalRemaining = $owedByUser->sum(fn (Debt $d) => Money::toCents($d->remaining_amount));
        $totalOriginal = $owedByUser->sum(fn (Debt $d) => Money::toCents($d->original_amount));
        $monthlyPayments = $owedByUser->sum(fn (Debt $d) => Money::toCents($this->monthlyPaymentAmount($d)));
        $owedToUser = $owedToUser->sum(fn (Debt $d) => Money::toCents($d->remaining_amount));

        return [
            'total_remaining' => Money::fromCents($totalRemaining),
            'total_original' => Money::fromCents($totalOriginal),
            'monthly_payments' => Money::fromCents((int) $monthlyPayments),
            'owed_to_user' => Money::fromCents($owedToUser),
            'progress' => $totalOriginal > 0 ? round((($totalOriginal - $totalRemaining) / $totalOriginal) * 100, 1) : 0,
            'count' => $owedByUser->count(),
        ];
    }

    /**
     * Scheduled debt installments within a window (for Safe to Spend /
     * Forecast / notifications).
     */
    public function amountBetween(int $userId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $debts = Debt::query()
            ->where('user_id', $userId)
            ->where('status', '!=', Debt::STATUS_PAID_OFF)
            ->where('type', '!=', Debt::TYPE_OWED_TO_USER)
            ->get();

        $totalCents = 0;
        $items = [];
        foreach ($debts as $debt) {
            $amountCents = Money::toCents($this->monthlyPaymentAmount($debt));
            $count = $this->paymentsInWindow($debt, $from, $to);
            if ($count === 0 || $amountCents === 0) {
                continue;
            }
            $sum = $amountCents * $count;
            $totalCents += $sum;
            $items[] = [
                'name' => $debt->name,
                'amount' => Money::fromCents($sum),
                'occurrences' => $count,
            ];
        }

        return ['total_cents' => $totalCents, 'items' => $items];
    }

    private function paymentsInWindow(Debt $debt, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $next = $debt->next_payment_date ? CarbonImmutable::parse($debt->next_payment_date) : null;
        if (! $next) {
            return 0;
        }

        $count = 0;
        $guard = 0;
        $cursor = $next->startOfDay();
        while ($cursor->lte($to) && $guard < 2000) {
            if ($cursor->gte($from)) {
                $count++;
            }
            $cursor = $this->advance($cursor, $debt->frequency);
            $guard++;
        }

        return $count;
    }

    private function paymentsRemaining(Debt $debt): int
    {
        $payment = Money::toCents($this->monthlyPaymentAmount($debt));
        $remaining = Money::toCents($debt->remaining_amount);

        if ($payment <= 0) {
            return 0;
        }

        return (int) ceil($remaining / $payment);
    }

    private function progress(Debt $debt): float
    {
        $original = Money::toCents($debt->original_amount);
        $remaining = Money::toCents($debt->remaining_amount);

        if ($original <= 0) {
            return 0;
        }

        return round((($original - $remaining) / $original) * 100, 1);
    }

    private function advance(CarbonImmutable $date, ?string $frequency): CarbonImmutable
    {
        return match ($frequency) {
            Debt::FREQ_WEEKLY => $date->addWeek(),
            Debt::FREQ_YEARLY => $date->addYear(),
            default => $date->addMonth(),
        };
    }
}
