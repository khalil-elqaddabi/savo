<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\SavingsGoal;
use App\Services\SavingsGoalService;
use App\Services\TransferService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SavingsGoalController extends Controller
{
    public function __construct(
        private SavingsGoalService $goals,
        private TransferService $transfers,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $statuses = $user->savingsGoals()->orderBy('is_completed')->orderBy('deadline')->get()
            ->map(fn ($g) => $this->goals->status($g) + [
                'id' => $g->id,
                'name' => $g->name,
                'target' => $g->target_amount,
                'account_id' => $g->account_id,
                'icon' => $g->icon,
                'color' => $g->color,
                'description' => $g->description,
                'deadline' => $g->deadline?->toDateString(),
                'is_completed' => $g->is_completed,
            ]);

        $accounts = Account::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'balance']);

        return Inertia::render('Goals/Index', [
            'goals' => $statuses->values(),
            'accounts' => $accounts->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'type' => $a->type,
                'balance' => $a->balance,
            ])->values(),
            'totalTarget' => number_format($statuses->sum(fn ($g) => (float) ($g['target'] ?? 0)), 2, '.', ''),
            'totalSaved' => number_format($statuses->sum(fn ($g) => (float) ($g['saved'] ?? 0)), 2, '.', ''),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $goal = $request->user()->savingsGoals()->create($data);
        $this->goals->ensureAccount($goal);

        return back()->with('success', __('Goal created.'));
    }

    public function update(Request $request, SavingsGoal $goal)
    {
        $this->authorize('update', $goal);

        $data = $this->validated($request);
        $goal->update($data);

        return back()->with('success', __('Goal updated.'));
    }

    public function contribute(Request $request, SavingsGoal $goal)
    {
        $this->authorize('update', $goal);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'account_id' => ['required', 'integer'],
        ]);

        $amount = $data['amount'];

        // The contribution is a transfer from the chosen source account into
        // the goal's dedicated savings sub-account. This makes the allocation
        // real (source balance decreases, ledger-backed) without reducing the
        // user's total net worth (it is a transfer, not an expense).
        $source = Account::query()
            ->where('user_id', $goal->user_id)
            ->where('id', $data['account_id'])
            ->firstOrFail();

        $destination = $this->goals->ensureAccount($goal);

        $this->transfers->create($source, $destination, [
            'amount' => $amount,
            'date' => now()->toDateString(),
            'description' => __('Savings goal contribution') . ": {$goal->name}",
        ]);

        $current = (float) $goal->current_amount + (float) $amount;
        $goal->current_amount = number_format($current, 2, '.', '');

        if ((float) $goal->current_amount >= (float) $goal->target_amount) {
            $goal->is_completed = true;
            $goal->achieved_at = now();
        }

        $goal->save();

        return back()->with('success', __('Contribution added.'));
    }

    public function destroy(Request $request, SavingsGoal $goal)
    {
        $this->authorize('delete', $goal);

        $goal->delete();

        return back()->with('success', __('Goal deleted.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'target_amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'current_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'deadline' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:40'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);
    }
}
