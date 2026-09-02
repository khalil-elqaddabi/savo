<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Imports transactions for a single user from a CSV upload.
 *
 * Accounts and categories are resolved by name, scoped to the current user, and
 * newly encountered values are created for that user only — never shared.
 */
class DataImportService
{
    public function importTransactions(User $user, string $content): int
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $header = fgetcsv($stream);
        if (! $header) {
            throw ValidationException::withMessages([
                'file' => __('CSV file is empty.'),
            ]);
        }

        $header = array_map(fn ($h) => strtolower(trim($h)), $header);

        $accountCache = $this->accountCache($user);
        $categoryCache = $this->categoryCache($user);

        $imported = 0;
        $rows = [];

        while (($row = fgetcsv($stream)) !== false) {
            $row = array_pad($row, count($header), null);
            $data = array_combine($header, $row);

            if (empty(array_filter($data))) {
                continue;
            }

            $rows[] = $this->normalizeRow($data);
        }

        fclose($stream);

        DB::transaction(function () use ($user, $rows, &$accountCache, &$categoryCache, &$imported) {
            foreach ($rows as $r) {
                $accountId = $this->resolveAccount($user, $r['account'], $accountCache);
                $categoryId = $this->resolveCategory($user, $r['category'], $categoryCache);

                Transaction::query()->create([
                    'user_id' => $user->id,
                    'account_id' => $accountId,
                    'category_id' => $categoryId,
                    'type' => $r['type'],
                    'amount' => $r['amount'],
                    'date' => $r['date'],
                    'description' => $r['description'],
                    'merchant' => $r['merchant'],
                    'is_transfer' => false,
                ]);

                $imported++;
            }
        });

        return $imported;
    }

    private function normalizeRow(array $data): array
    {
        $type = strtolower(trim((string) ($data['type'] ?? 'expense')));

        if (! in_array($type, [Transaction::TYPE_INCOME, Transaction::TYPE_EXPENSE, Transaction::TYPE_TRANSFER], true)) {
            $type = Transaction::TYPE_EXPENSE;
        }

        return [
            'type' => $type,
            'amount' => $this->parseAmount($data['amount'] ?? 0),
            'date' => $this->parseDate($data['date'] ?? null),
            'description' => $data['description'] ?? null,
            'merchant' => $data['merchant'] ?? null,
            'account' => $data['account'] ?? null,
            'category' => $data['category'] ?? null,
        ];
    }

    private function parseAmount(mixed $value): float
    {
        $v = trim((string) $value);
        return (float) str_replace([',', ' '], '', $v);
    }

    private function parseDate(mixed $value): string
    {
        if (! $value) {
            return now()->toDateString();
        }

        try {
            return \Carbon\CarbonImmutable::parse($value)->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    private function accountCache(User $user): array
    {
        return $user->accounts()->pluck('id', 'name')->all();
    }

    private function categoryCache(User $user): array
    {
        return Category::query()->forUser($user->id)->pluck('id', 'name')->all();
    }

    private function resolveAccount(User $user, ?string $name, array &$cache): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        if (isset($cache[$name])) {
            return $cache[$name];
        }

        $account = $user->accounts()->create([
            'name' => $name,
            'type' => 'bank',
            'balance' => 0,
            'starting_balance' => 0,
            'is_active' => true,
            'is_archived' => false,
        ]);

        return $cache[$name] = $account->id;
    }

    private function resolveCategory(User $user, ?string $name, array &$cache): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        if (isset($cache[$name])) {
            return $cache[$name];
        }

        $category = Category::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'type' => 'expense',
            'is_system' => false,
            'is_active' => true,
        ]);

        return $cache[$name] = $category->id;
    }
}
