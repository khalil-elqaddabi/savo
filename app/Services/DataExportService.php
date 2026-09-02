<?php

namespace App\Services;

use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse as BaseStreamedResponse;

/**
 * Exports a single user's data to CSV, streaming it back to the browser.
 */
class DataExportService
{
    /**
     * Stream the user's transactions as a CSV download.
     *
     * @return BaseStreamedResponse
     */
    public function transactionsCsv(User $user): BaseStreamedResponse
    {
        return response()->streamDownload(function () use ($user) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'date', 'type', 'amount', 'description', 'merchant',
                'account', 'destination', 'category',
            ]);

            $user->transactions()
                ->with(['account', 'destinationAccount', 'category'])
                ->orderBy('date')
                ->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $tx) {
                        fputcsv($out, [
                            $tx->date?->toDateString(),
                            $tx->type,
                            $tx->amount,
                            $tx->description,
                            $tx->merchant,
                            $tx->account?->name,
                            $tx->destinationAccount?->name,
                            $tx->category?->name,
                        ]);
                    }
                });

            fclose($out);
        }, 'transactions-' . $user->id . '-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
