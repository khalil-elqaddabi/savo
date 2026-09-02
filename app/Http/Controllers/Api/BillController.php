<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Services\BillService;
use Illuminate\Http\Request;

class BillController extends Controller
{
    public function __construct(private BillService $bills)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $items = $user->bills()
            ->with('category', 'account')
            ->orderByRaw(
                "CASE status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 WHEN 'cancelled' THEN 2 ELSE 3 END"
            )
            ->orderBy('next_payment_date')
            ->get();

        return response()->json([
            'data' => [
                'bills' => $items->map(fn (Bill $bill) => [
                    'id' => $bill->id,
                    'name' => $bill->name,
                    'amount' => $bill->amount,
                    'currency' => $bill->currency,
                    'category_id' => $bill->category_id,
                    'category' => $bill->category?->name,
                    'account_id' => $bill->account_id,
                    'frequency' => $bill->frequency,
                    'interval' => $bill->interval,
                    'next_payment_date' => $bill->next_payment_date->toDateString(),
                    'start_date' => $bill->start_date?->toDateString(),
                    'end_date' => $bill->end_date?->toDateString(),
                    'status' => $bill->status,
                    'notes' => $bill->notes,
                    'monthly_amount' => $this->bills->monthlyAmount($bill),
                ]),
                'monthly_cost' => $this->bills->monthlyCost($user->id),
                'yearly_cost' => $this->bills->yearlyCost($user->id),
                'active_count' => $user->bills()->where('status', Bill::STATUS_ACTIVE)->count(),
                'upcoming' => $this->bills->upcoming($user->id, limit: 6),
                'accounts' => $user->activeAccounts()->orderBy('name')->get(['id', 'name']),
                'categories' => \App\Support\CategoryCatalog::for($user->id),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $bill = $request->user()->bills()->create($data);
        $bill->load('category', 'account');

        return response()->json([
            'data' => ['bill' => $bill],
            'message' => __('Bill created.'),
        ], 201);
    }

    public function show(Request $request, Bill $bill)
    {
        $this->authorize('view', $bill);

        $bill->load('category', 'account');

        return response()->json(['data' => ['bill' => $bill]]);
    }

    public function update(Request $request, Bill $bill)
    {
        $this->authorize('update', $bill);

        $bill->update($this->validated($request));
        $bill->load('category', 'account');

        return response()->json([
            'data' => ['bill' => $bill],
            'message' => __('Bill updated.'),
        ]);
    }

    public function destroy(Request $request, Bill $bill)
    {
        $this->authorize('delete', $bill);

        $bill->delete();

        return response()->json(['message' => __('Bill deleted.')]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'category_id' => ['nullable', 'integer'],
            'account_id' => ['nullable', 'integer'],
            'frequency' => ['required', 'in:daily,weekly,monthly,yearly'],
            'interval' => ['nullable', 'integer', 'min:1', 'max:30'],
            'next_payment_date' => ['required', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'in:active,paused,cancelled'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}