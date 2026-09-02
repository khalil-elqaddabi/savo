<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\BudgetService;
use App\Services\TransactionService;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function __construct(
        private BudgetService $budgets,
        private TransactionService $transactions,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $overview = $this->budgets->currentOverview($user->id);

        $thisMonthExpenses = Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', Transaction::TYPE_EXPENSE)
            ->whereBetween('date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('amount');

        $categories = Category::query()
            ->where(function ($q) use ($user) {
                $q->whereNull('user_id')->orWhere('user_id', $user->id);
            })
            ->where('type', 'expense')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'icon', 'color']);

        return response()->json([
            'data' => [
                'budgets' => $overview->map(fn ($s) => [
                    'id' => $s['budget']->id,
                    'name' => $s['budget']->name,
                    'scope' => $s['budget']->scope,
                    'period' => $s['budget']->period,
                    'category_id' => $s['budget']->category_id,
                    'category' => $s['budget']->category?->name,
                    'amount' => $s['amount'],
                    'spent' => $s['spent'],
                    'remaining' => $s['remaining'],
                    'percent' => $s['percent'],
                    'status' => $s['status'],
                    'period_start' => $s['period_start'],
                    'period_end' => $s['period_end'],
                ])->values(),
                'this_month_expenses' => $thisMonthExpenses ?: '0.00',
                'categories' => CategoryResource::collection($categories),
            ],
        ]);
    }

    public function show(Request $request, Budget $budget)
    {
        $this->authorize('view', $budget);

        $status = $this->budgets->status($budget);

        return response()->json([
            'data' => [
                'budget' => [
                    'id' => $status['budget']->id,
                    'name' => $status['budget']->name,
                    'scope' => $status['budget']->scope,
                    'period' => $status['budget']->period,
                    'category_id' => $status['budget']->category_id,
                    'category' => $status['budget']->category?->name,
                    'amount' => $status['amount'],
                    'spent' => $status['spent'],
                    'remaining' => $status['remaining'],
                    'percent' => $status['percent'],
                    'status' => $status['status'],
                    'period_start' => $status['period_start'],
                    'period_end' => $status['period_end'],
                ],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        if ($data['scope'] === 'category') {
            $request->validate(['category_id' => ['required', 'integer']]);
        }

        $budget = $request->user()->budgets()->create($data);

        return response()->json([
            'data' => ['budget' => $budget],
            'message' => __('Budget created.'),
        ], 201);
    }

    public function update(Request $request, Budget $budget)
    {
        $this->authorize('update', $budget);

        $data = $this->validated($request);
        $budget->update($data);

        return response()->json([
            'data' => ['budget' => $budget->only(['id', 'name', 'scope', 'period', 'category_id', 'amount', 'is_active'])],
            'message' => __('Budget updated.'),
        ]);
    }

    public function destroy(Request $request, Budget $budget)
    {
        $this->authorize('delete', $budget);

        $budget->delete();

        return response()->json(['message' => __('Budget deleted.')]);
    }

    public function spend(Request $request, Budget $budget)
    {
        $this->authorize('update', $budget);

        $data = $request->validate([
            'account_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $account = Account::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($data['account_id']);

        $transaction = $this->transactions->createExpense($account, [
            'category_id' => $budget->scope === 'category' ? $budget->category_id : null,
            'amount' => $data['amount'],
            'date' => $data['date'] ?? now()->toDateString(),
            'description' => $data['description'] ?? __('Budget') . ": {$budget->name}",
        ]);

        $transaction->load(['account', 'destinationAccount', 'category']);

        return response()->json([
            'data' => ['transaction' => new \App\Http\Resources\TransactionResource($transaction)],
            'message' => __('Expense recorded for budget.'),
        ], 201);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'scope' => ['required', 'in:overall,category'],
            'category_id' => ['nullable', 'integer'],
            'period' => ['required', 'in:weekly,monthly'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'is_active' => ['boolean'],
        ]);
    }
}