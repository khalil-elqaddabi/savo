<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use App\Services\AccountBalanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountController extends Controller
{
    public function __construct(private AccountBalanceService $balances)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $accounts = $user->activeAccounts()->withCount([
            'transactions' => fn ($q) => $q->where('type', '!=', 'transfer'),
        ])->orderBy('balance', 'desc')->get();

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'type' => $a->type,
                'balance' => $a->balance,
                'icon' => $a->icon,
                'color' => $a->color,
                'description' => $a->description,
                'transactions_count' => $a->transactions_count,
                'currency' => $a->currency,
            ])->values(),
            'totalBalance' => $this->balances->totalBalance($user->id),
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

        return Inertia::render('Accounts/Show', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'balance' => $balance,
                'starting_balance' => $account->starting_balance,
                'icon' => $account->icon,
                'color' => $account->color,
                'description' => $account->description,
                'currency' => $account->currency,
                'institution' => $account->institution,
            ],
            'transactions' => $transactions->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'amount' => $t->amount,
                'date' => $t->date->toDateString(),
                'description' => $t->description,
                'account' => $t->account?->name,
                'destination' => $t->destinationAccount?->name,
                'category' => $t->category?->name,
                'category_icon' => $t->category?->icon,
                'category_color' => $t->category?->color,
            ])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        // Persist the initial balance as the starting balance too, so the
        // amount survives the deterministic ledger recompute (otherwise the
        // first transaction would drop the initial amount down to zero).
        $data['starting_balance'] = $data['balance'] ?? 0;

        $account = $request->user()->accounts()->create($data);

        return back()->with('success', __('Account created.'));
    }

    public function update(Request $request, Account $account)
    {
        $this->authorize('update', $account);

        $data = $this->validated($request);

        $account->update($data);

        return back()->with('success', __('Account updated.'));
    }

    public function destroy(Request $request, Account $account)
    {
        $this->authorize('delete', $account);

        $account->delete();
        $this->balances->refreshAllForUser($request->user()->id);

        return redirect()->route('accounts.index')->with('success', __('Account deleted.'));
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
