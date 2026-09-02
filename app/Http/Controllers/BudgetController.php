<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BudgetController extends Controller
{
    public function __construct(private BudgetService $budgets)
    {
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

        return Inertia::render('Budgets/Index', [
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
                'is_owner' => $s['is_owner'],
                'role' => $s['role'],
                'members' => $s['members'],
            ])->values(),
            'thisMonthExpenses' => $thisMonthExpenses ?: '0.00',
            'categories' => $categories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'icon' => $c->icon,
                'color' => $c->color,
            ])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        if ($data['scope'] === 'category') {
            $request->validate(['category_id' => ['required', 'integer']]);
        }

        $request->user()->budgets()->create($data);

        return back()->with('success', __('Budget created.'));
    }

    public function update(Request $request, Budget $budget)
    {
        $this->authorize('update', $budget);

        $data = $this->validated($request);
        $budget->update($data);

        return back()->with('success', __('Budget updated.'));
    }

    public function destroy(Request $request, Budget $budget)
    {
        $this->authorize('delete', $budget);

        $budget->delete();

        return back()->with('success', __('Budget deleted.'));
    }

    /**
     * Share a budget with another registered user (owner only).
     */
    public function addMember(Request $request, Budget $budget)
    {
        $this->authorize('manageMembers', $budget);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:viewer,editor'],
        ]);

        $member = User::query()->where('email', $data['email'])->first();

        if (! $member) {
            return back()->withErrors(['email' => __('No account exists for this email address.')]);
        }

        if ((int) $member->id === (int) $budget->user_id) {
            return back()->withErrors(['email' => __('The budget owner cannot be added as a member.')]);
        }

        $budget->members()->firstOrCreate(
            ['user_id' => $member->id],
            ['role' => $data['role']],
        );

        return back()->with('success', __('Budget shared.'));
    }

    /**
     * Remove a member from a shared budget (owner only).
     */
    public function removeMember(Request $request, Budget $budget, int $user)
    {
        $this->authorize('manageMembers', $budget);

        $budget->members()->where('user_id', $user)->delete();

        return back()->with('success', __('Member removed.'));
    }

    /**
     * Leave a shared budget as the currently authenticated member. Only that
     * member's own budget_shares row is removed — the budget and everyone else
     * stay intact. The budget owner is barred by the policy.
     */
    public function leaveMember(Request $request, Budget $budget)
    {
        $this->authorize('leave', $budget);

        $budget->members()
            ->where('user_id', $request->user()->id)
            ->delete();

        return back()->with('success', __('You have left the budget.'));
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
