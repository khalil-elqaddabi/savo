<?php

namespace App\Http\Controllers;

use App\Services\AccountDataService;
use App\Services\DataExportService;
use App\Services\DataImportService;
use Illuminate\Http\Request;

class AccountDataController extends Controller
{
    public function __construct(
        private AccountDataService $data,
        private DataExportService $export,
        private DataImportService $import,
    ) {
    }

    public function export(Request $request)
    {
        return $this->export->transactionsCsv($request->user());
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $content = $request->file('file')->get();

        $count = $this->import->importTransactions($request->user(), $content);

        if ($count === 0) {
            return back()->with('error', __('No transactions were imported.'));
        }

        return back()->with('success', __('Imported :count transactions.', ['count' => $count]));
    }

    public function reset(Request $request)
    {
        $user = $request->user();

        // Password-verified accounts must re-confirm their current password.
        // Accounts that authenticate through a supported OAuth provider have no
        // usable local password, so the already-authenticated session plus an
        // explicit confirmation is used instead.
        $rules = [];
        if (! $user->usesGoogleAuth()) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        $request->validate($rules);

        $this->data->reset($user);

        return back()->with('success', __('Account data reset.'));
    }
}
