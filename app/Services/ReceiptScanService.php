<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;
use App\Services\AI\OpenAIService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Receipt scanner: uses the configured AI provider's vision capability to turn
 * an uploaded receipt image into a structured transaction draft.
 *
 * The scan is read-only; it never persists anything itself. It returns a draft
 * the user confirms before a transaction is actually created.
 */
class ReceiptScanService
{
    public function __construct(private OpenAIService $ai)
    {
    }

    /**
     * Recognise currency formatting a provider may return as a symbol or code.
     */
    public function normalizeCurrency(?string $currency, User $user): string
    {
        $raw = strtoupper(trim((string) $currency));

        $symbols = [
            '$' => 'USD',
            '€' => 'EUR',
            '£' => 'GBP',
            'DH' => 'MAD',
            'د.م.' => 'MAD',
            'درهم' => 'MAD',
        ];

        foreach ($symbols as $symbol => $code) {
            if (Str::contains($raw, $symbol)) {
                return $code;
            }
        }

        if (strlen($raw) === 3 && ctype_alpha($raw)) {
            return $raw;
        }

        return $user->getFinancialSetting()?->currency ?? 'MAD';
    }

    /**
     * @return array{
     *   amount: float,
     *   currency: string,
     *   merchant: string,
     *   category: string,
     *   date: string,
     *   type: string
     * }
     */
    public function scan(User $user, UploadedFile $file): array
    {
        if (! $this->ai->isConfigured()) {
            throw new RuntimeException('Receipt scanning is unavailable: the AI provider is not configured.');
        }

        $mime = $file->getMimeType() ?? 'image/jpeg';
        $bytes = $file->get();

        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('The uploaded receipt could not be read.');
        }

        $dataUrl = 'data:'.$mime.';base64,'.base64_encode($bytes);

        $raw = $this->ai->scanReceipt($dataUrl);

        if (isset($raw['error'])) {
            throw new RuntimeException('The receipt could not be read. Try a clearer photo.');
        }

        $amount = (float) ($raw['amount'] ?? 0);
        if ($amount <= 0) {
            throw new RuntimeException('The receipt could not be read. Try a clearer photo.');
        }

        $type = strtolower((string) ($raw['type'] ?? 'expense')) === 'income'
            ? 'income'
            : 'expense';

        return [
            'amount' => round($amount, 2),
            'currency' => $this->normalizeCurrency($raw['currency'] ?? null, $user),
            'merchant' => trim((string) ($raw['merchant'] ?? '')),
            'category' => $this->resolveCategory(trim((string) ($raw['category'] ?? ''))),
            'date' => $this->normalizeDate($raw['date'] ?? null),
            'type' => $type,
        ];
    }

    private function resolveCategory(string $raw): string
    {
        return $raw !== '' ? $raw : '';
    }

    private function normalizeDate(mixed $date): ?string
    {
        if (! $date) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($date)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
