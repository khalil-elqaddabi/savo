<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccountResource;
use App\Http\Resources\TransactionResource;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\AccountBalanceService;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(private AccountBalanceService $balances)
    {
    }

    public function index(Request $request)
    {
        $accounts = $request->user()->activeAccounts()
            ->withCount(['transactions' => fn ($q) => $q->where('type', '!=', 'transfer')])
            ->orderBy('balance', 'desc')
            ->get();

        return response()->json([
            'data' => [
                'accounts' => AccountResource::collection($accounts),
                'total_balance' => $this->balances->totalBalance($request->user()->id),
            ],
        ]);
    }

    public function show(Request $request, Account $account)
    {
        $this->authorize('view', $account);

        $balance = $this->balances->computeBalance($account);

        $transactions = Transaction::query()
            ->where('user_id', $request->user()->id)
            ->where(function ($q) use ($account) {
                $q->where('account_id', $account->id)
                    ->orWhere('destination_account_id', $account->id);
            })
            ->with(['account', 'destinationAccount', 'category'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return response()->json([
            'data' => [
                'account' => AccountResource::make($account)->additional([
                    'computed_balance' => $balance,
                ]),
                'transactions' => TransactionResource::collection($transactions),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $data['starting_balance'] = $data['balance'] ?? 0;

        $account = $request->user()->accounts()->create($data);

        $this->balances->refresh($account);

        return response()->json([
            'data' => ['account' => new AccountResource($account)],
        ], 201);
    }

    public function update(Request $request, Account $account)
    {
        $this->authorize('update', $account);

        $data = $this->validated($request);
        $account->update($data);

        return response()->json([
            'data' => ['account' => new AccountResource($account)],
        ]);
    }

    public function destroy(Request $request, Account $account)
    {
        $this->authorize('delete', $account);

        $account->delete();
        $this->balances->refreshAllForUser($request->user()->id);

        return response()->json(['message' => __('Account deleted.')]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:' . implode(',', array_keys(Account::$types))],
            'balance' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'icon' => ['nullable', 'string', 'max:40'],
            'color' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:500'],
            'institution' => ['nullable', 'string', 'max:120'],
        ]);
    }
}