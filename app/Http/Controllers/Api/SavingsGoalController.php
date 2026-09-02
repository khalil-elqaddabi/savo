<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\SavingsGoal;
use App\Services\SavingsGoalService;
use App\Services\TransferService;
use Illuminate\Http\Request;

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

        return response()->json([
            'data' => [
                'goals' => $statuses->values(),
                'accounts' => $accounts->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'type' => $a->type,
                    'balance' => $a->balance,
                ])->values(),
                'total_target' => number_format($statuses->sum(fn ($g) => (float) ($g['target'] ?? 0)), 2, '.', ''),
                'total_saved' => number_format($statuses->sum(fn ($g) => (float) ($g['saved'] ?? 0)), 2, '.', ''),
            ],
        ]);
    }

    public function show(Request $request, SavingsGoal $goal)
    {
        $this->authorize('view', $goal);

        $status = $this->goals->status($goal);

        return response()->json([
            'data' => ['goal' => $status + [
                'id' => $goal->id,
                'name' => $goal->name,
                'target' => $goal->target_amount,
                'account_id' => $goal->account_id,
                'icon' => $goal->icon,
                'color' => $goal->color,
                'description' => $goal->description,
                'deadline' => $goal->deadline?->toDateString(),
                'is_completed' => $goal->is_completed,
            ]],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $goal = $request->user()->savingsGoals()->create($data);
        $this->goals->ensureAccount($goal);

        return response()->json([
            'data' => ['goal' => $goal->only(['id', 'name', 'target_amount', 'deadline', 'description', 'icon', 'color'])],
            'message' => __('Goal created.'),
        ], 201);
    }

    public function update(Request $request, SavingsGoal $goal)
    {
        $this->authorize('update', $goal);

        $data = $this->validated($request);
        $goal->update($data);

        return response()->json([
            'data' => ['goal' => $goal->only(['id', 'name', 'target_amount', 'deadline', 'description', 'icon', 'color'])],
            'message' => __('Goal updated.'),
        ]);
    }

    public function destroy(Request $request, SavingsGoal $goal)
    {
        $this->authorize('delete', $goal);

        $goal->delete();

        return response()->json(['message' => __('Goal deleted.')]);
    }

    public function contribute(Request $request, SavingsGoal $goal)
    {
        $this->authorize('update', $goal);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'account_id' => ['required', 'integer'],
        ]);

        $source = Account::query()
            ->where('user_id', $goal->user_id)
            ->where('id', $data['account_id'])
            ->firstOrFail();

        $destination = $this->goals->ensureAccount($goal);

        $this->transfers->create($source, $destination, [
            'amount' => $data['amount'],
            'date' => now()->toDateString(),
            'description' => __('Savings goal contribution') . ": {$goal->name}",
        ]);

        $current = (float) $goal->current_amount + (float) $data['amount'];
        $goal->current_amount = number_format($current, 2, '.', '');

        if ((float) $goal->current_amount >= (float) $goal->target_amount) {
            $goal->is_completed = true;
            $goal->achieved_at = now();
        }

        $goal->save();

        $status = $this->goals->status($goal->fresh());

        return response()->json([
            'data' => ['goal' => $status + [
                'id' => $goal->id,
                'name' => $goal->name,
                'target' => $goal->target_amount,
                'account_id' => $goal->account_id,
                'icon' => $goal->icon,
                'color' => $goal->color,
                'description' => $goal->description,
                'deadline' => $goal->deadline?->toDateString(),
                'is_completed' => $goal->is_completed,
            ]],
            'message' => __('Contribution added.'),
        ]);
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