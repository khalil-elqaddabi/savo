<?php

namespace App\Http\Controllers;

use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Services\RecurringTransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RecurringTransactionController extends Controller
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

        return Inertia::render('Recurring/Index', [
            'recurring' => $items->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'type' => $r->type,
                'amount' => $r->amount,
                'frequency' => $r->frequency,
                'next_occurrence' => $r->next_occurrence->toDateString(),
                'start_date' => $r->start_date->toDateString(),
                'end_date' => $r->end_date?->toDateString(),
                'is_active' => $r->is_active,
                'account' => $r->account?->name,
                'category' => $r->category?->name,
                'category_icon' => $r->category?->icon,
            ])->values(),
            'monthlyIncome' => $monthly['income'],
            'monthlyExpense' => $monthly['expense'],
            'accounts' => $request->user()->activeAccounts()->orderBy('name')->get(['id', 'name']),
            'categories' => \App\Models\Category::query()
                ->where(function ($q) use ($user) {
                    $q->whereNull('user_id')->orWhere('user_id', $user->id);
                })
                ->where('is_active', true)
                ->get(['id', 'name', 'type', 'icon'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'type' => $c->type, 'icon' => $c->icon]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->defaultNextOccurrence($data);

        $request->user()->recurringTransactions()->create($data);

        return back()->with('success', __('Recurring transaction created.'));
    }

    public function update(Request $request, RecurringTransaction $recurring)
    {
        $this->authorize('update', $recurring);

        $data = $this->validated($request);
        $this->defaultNextOccurrence($data);
        $recurring->update($data);

        return back()->with('success', __('Recurring transaction updated.'));
    }

    public function destroy(Request $request, RecurringTransaction $recurring)
    {
        $this->authorize('delete', $recurring);

        $recurring->delete();

        return back()->with('success', __('Recurring transaction deleted.'));
    }

    private function defaultNextOccurrence(array &$data): void
    {
        // The DB column is NOT NULL but the field is intentionally optional.
        // When omitted, anchor the next occurrence on the start date (this
        // matches RecurringTransactionService, which falls back to start_date).
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
