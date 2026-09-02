<?php

namespace App\Http\Controllers;

use App\Services\ReceiptScanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ReceiptScanController extends Controller
{
    public function __construct(private ReceiptScanService $scanner)
    {
    }

    public function scan(Request $request)
    {
        $validated = $request->validate([
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        try {
            $draft = $this->scanner->scan($request->user(), $validated['receipt']);

            return back()->with('receiptDraft', $draft);
        } catch (RuntimeException $e) {
            Log::info('Receipt scan failed for user '.$request->user()->id.': '.$e->getMessage(), [
                'safe' => true,
            ]);

            return back()->with('error', $e->getMessage());
        }
    }
}
