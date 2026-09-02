<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use App\Services\TransactionService;
use App\Services\TransferService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransactionController extends Controller
{
    public function __construct(
        private TransactionService $transactions,
        private TransferService $transfers,
    ) {
    }

    public function formData(Request $request)
    {
        return response()->json($this->formDataFor($request->user()->id));
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Transaction::query()
            ->where('user_id', $user->id)
            ->with(['account', 'destinationAccount', 'category']);

        if ($search = $request->string('search')->trim()->toString()) {
            $needle = '%'.mb_strtolower($search).'%';

            $query->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(description) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(merchant) LIKE ?', [$needle]);
            });
        }

        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }

        if ($categoryId = $request->integer('category')) {
            $query->where('category_id', $categoryId);
        }

        if ($accountId = $request->integer('account')) {
            $query->where(function ($q) use ($accountId) {
                $q->where('account_id', $accountId)->orWhere('destination_account_id', $accountId);
            });
        }

        if ($from = $request->string('from')->toString()) {
            $query->whereDate('date', '>=', $from);
        }

        if ($to = $request->string('to')->toString()) {
            $query->whereDate('date', '<=', $to);
        }

        if ($request->filled('min_amount')) {
            $query->where('amount', '>=', $request->input('min_amount'));
        }

        if ($request->filled('max_amount')) {
            $query->where('amount', '<=', $request->input('max_amount'));
        }

        switch ($request->string('sort')->toString()) {
            case 'oldest':
                $query->orderBy('date')->orderBy('id');
                break;
            case 'highest':
                $query->orderByDesc('amount');
                break;
            case 'lowest':
                $query->orderBy('amount');
                break;
            default:
                $query->orderByDesc('date')->orderByDesc('id');
        }

        $transactions = $query->paginate(15)->withQueryString();

        return Inertia::render('Transactions/Index', [
            'transactions' => [
                'data' => $transactions->map(fn ($t) => [
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
                'meta' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'total' => $transactions->total(),
                    'per_page' => $transactions->perPage(),
                    'links' => $transactions->linkCollection()->toArray(),
                ],
            ],
            'filters' => [
                'search' => $request->string('search')->toString(),
                'type' => $request->string('type')->toString(),
                'category' => $request->integer('category'),
                'account' => $request->integer('account'),
                'from' => $request->string('from')->toString(),
                'to' => $request->string('to')->toString(),
                'min_amount' => $request->string('min_amount')->toString(),
                'max_amount' => $request->string('max_amount')->toString(),
                'sort' => $request->string('sort')->toString(),
            ],
            ...$this->formDataFor($user->id),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $account = Account::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($data['account_id']);

        if ($data['type'] === Transaction::TYPE_TRANSFER) {
            $destination = Account::query()
                ->where('user_id', $request->user()->id)
                ->findOrFail($data['destination_account_id']);

            $this->transfers->create($account, $destination, $data);

            return back()->with('success', __('Transfer recorded.'));
        }

        if ($data['type'] === Transaction::TYPE_INCOME) {
            $this->transactions->createIncome($account, $data);
        } else {
            $this->transactions->createExpense($account, $data);
        }

        return back()->with('success', __('Transaction recorded.'));
    }

    public function update(Request $request, Transaction $transaction)
    {
        $this->authorize('update', $transaction);

        $data = $this->validated($request);

        $account = Account::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($data['account_id']);

        $this->transactions->update($transaction, $account, $data);

        return back()->with('success', __('Transaction updated.'));
    }

    public function destroy(Request $request, Transaction $transaction)
    {
        $this->authorize('delete', $transaction);

        if ($transaction->isTransfer()) {
            $this->transfers->delete($transaction);
        } else {
            $this->transactions->delete($transaction);
        }

        return back()->with('success', __('Transaction deleted.'));
    }

    private function validated(Request $request): array
    {
        $rules = [
            'type' => ['required', 'in:income,expense,transfer'],
            'account_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ];

        if ($request->input('type') === 'income' || $request->input('type') === 'expense') {
            $rules['category_id'] = ['nullable', 'integer'];
        }

        if ($request->input('type') === 'transfer') {
            $rules['destination_account_id'] = ['required', 'integer', 'different:account_id'];
        }

        return $request->validate($rules);
    }

    private function formDataFor(int $userId): array
    {
        return [
            'accounts' => Account::query()
                ->where('user_id', $userId)
                ->where('is_archived', false)
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'balance'])
                ->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'type' => $a->type, 'balance' => $a->balance])
                ->values(),
            'categories' => \App\Models\Category::query()
                ->where(function ($q) use ($userId) {
                    $q->whereNull('user_id')->orWhere('user_id', $userId);
                })
                ->where('is_active', true)
                ->orderBy('is_system', 'desc')
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'icon', 'color', 'is_system'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'type' => $c->type, 'icon' => $c->icon, 'color' => $c->color])
                ->values(),
        ];
    }
}
