<?php

namespace App\Http\Controllers;

use App\Models\Debt;
use App\Services\DebtService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DebtController extends Controller
{
    public function __construct(private DebtService $debts)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Debts/Index', [
            'debts' => $this->debts->all($user->id),
            'summary' => $this->debts->summary($user->id),
            'accounts' => $user->activeAccounts()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        if (! isset($data['remaining_amount'])) {
            $data['remaining_amount'] = $data['original_amount'];
        }

        $request->user()->debts()->create($data);

        return back()->with('success', __('Debt created.'));
    }

    public function update(Request $request, Debt $debt)
    {
        $this->authorize('update', $debt);

        $debt->update($this->validated($request));

        return back()->with('success', __('Debt updated.'));
    }

    public function destroy(Request $request, Debt $debt)
    {
        $this->authorize('delete', $debt);

        $debt->delete();

        return back()->with('success', __('Debt deleted.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:personal,loan,credit,owed_to_user,owed_to_others'],
            'original_amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'remaining_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'installment_amount' => ['nullable', 'numeric', 'gt:0', 'max:9999999999999'],
            'frequency' => ['nullable', 'in:weekly,monthly,yearly'],
            'next_payment_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'account_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:active,paid_off,paused'],
        ]);
    }
}