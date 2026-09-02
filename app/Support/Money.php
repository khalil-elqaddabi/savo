<?php

namespace App\Support;

/**
 * Deterministic money arithmetic using integer cents.
 *
 * Financial amounts are stored as DECIMAL(15,2) in the database. All
 * authoritative calculations in the application must go through this class
 * so that floating point imprecision can never corrupt a balance or total.
 */
final class Money
{
    /**
     * Convert a decimal amount (string or numeric) to integer cents.
     * Rounding HALF_UP ensures deterministic behaviour.
     */
    public static function toCents(int|float|string|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        if (is_int($amount)) {
            return $amount * 100;
        }

        $str = trim((string) $amount);

        $negative = str_starts_with($str, '-');
        $str = ltrim($str, '+-');

        if (str_contains($str, '.')) {
            [$wholeStr, $fracStr] = explode('.', $str, 2);
        } else {
            $wholeStr = $str;
            $fracStr = '';
        }

        $wholeStr = preg_replace('/[^0-9]/', '', $wholeStr);
        $fracStr = preg_replace('/[^0-9]/', '', $fracStr);

        // Keep up to 3 fractional digits (the third drives HALF_UP rounding).
        $fracStr = str_pad(substr($fracStr, 0, 3), 3, '0', STR_PAD_RIGHT);

        $hundredth = (int) substr($fracStr, 0, 2);
        $thousandth = (int) substr($fracStr, 2, 1);

        $cents = (int) $wholeStr * 100 + $hundredth;
        if ($thousandth >= 5) {
            $cents++;
        }

        return $negative ? -$cents : $cents;
    }

    public static function fromCents(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);
        $whole = intdiv($cents, 100);
        $fraction = $cents % 100;

        $result = sprintf('%d.%02d', $whole, $fraction);

        return $negative ? '-'.$result : $result;
    }

    public static function add(int|float|string ...$amounts): string
    {
        $total = 0;
        foreach ($amounts as $amount) {
            $total += self::toCents($amount);
        }

        return self::fromCents($total);
    }

    public static function sub(int|float|string $a, int|float|string $b): string
    {
        return self::fromCents(self::toCents($a) - self::toCents($b));
    }

    /** Multiply amount by a factor and round HALF_UP. */
    public static function mul(int|float|string $amount, int|float $factor): string
    {
        return self::fromCents((int) round(self::toCents($amount) * $factor, 0, PHP_ROUND_HALF_UP));
    }

    /** Divide amount by divisor and round HALF_UP to whole cents. */
    public static function div(int|float|string $amount, int|float $divisor): string
    {
        if ((float) $divisor == 0) {
            return '0.00';
        }

        return self::fromCents((int) round(self::toCents($amount) / $divisor, 0, PHP_ROUND_HALF_UP));
    }

    public static function isZero(int|float|string $amount): bool
    {
        return self::toCents($amount) === 0;
    }

    public static function compare(int|float|string $a, int|float|string $b): int
    {
        return self::toCents($a) <=> self::toCents($b);
    }
}
