<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\RecurringResource;
use App\Models\RecurringTransaction;
use App\Services\RecurringTransactionService;
use App\Support\CategoryCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class RecurringController extends Controller
{
    public function __construct(private RecurringTransactionService $recurring)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $items = $user->recurringTransactions()
            ->with('account', 'category')
            ->orderBy('is_active', 'desc')
            ->orderBy('next_occurrence')
            ->get();

        $monthly = $this->recurring->monthlySummaryForUser($user->id, CarbonImmutable::now());

        return response()->json([
            'data' => [
                'recurring' => RecurringResource::collection($items),
                'monthly_income' => $monthly['income'],
                'monthly_expense' => $monthly['expense'],
                'accounts' => $user->activeAccounts()->orderBy('name')->get(['id', 'name']),
                'categories' => CategoryResource::collection(
                    CategoryCatalog::for($user->id)
                ),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->defaultNextOccurrence($data);

        $recurring = $request->user()->recurringTransactions()->create($data);
        $recurring->load('account', 'category');

        return response()->json([
            'data' => ['recurring' => new RecurringResource($recurring)],
            'message' => __('Recurring transaction created.'),
        ], 201);
    }

    public function show(Request $request, RecurringTransaction $recurring)
    {
        $this->authorize('view', $recurring);

        $recurring->load('account', 'category');

        return response()->json([
            'data' => ['recurring' => new RecurringResource($recurring)],
        ]);
    }

    public function update(Request $request, RecurringTransaction $recurring)
    {
        $this->authorize('update', $recurring);

        $data = $this->validated($request);
        $this->defaultNextOccurrence($data);
        $recurring->update($data);
        $recurring->load('account', 'category');

        return response()->json([
            'data' => ['recurring' => new RecurringResource($recurring)],
            'message' => __('Recurring transaction updated.'),
        ]);
    }

    public function destroy(Request $request, RecurringTransaction $recurring)
    {
        $this->authorize('delete', $recurring);

        $recurring->delete();

        return response()->json(['message' => __('Recurring transaction deleted.')]);
    }

    private function defaultNextOccurrence(array &$data): void
    {
        if (blank($data['next_occurrence'] ?? null) && ! blank($data['start_date'] ?? null)) {
            $data['next_occurrence'] = $data['start_date'];
        }
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:income,expense'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'account_id' => ['required', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'frequency' => ['required', 'in:daily,weekly,monthly,yearly'],
            'interval' => ['nullable', 'integer', 'min:1', 'max:30'],
            'next_occurrence' => ['nullable', 'date'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['boolean'],
        ]);
    }
}