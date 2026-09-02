<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\BudgetShare;
use App\Models\User;

class BudgetPolicy
{
    use OwnedByUserPolicy;

    /**
     * A budget may be viewed by its owner or by any user it was shared with
     * (both viewers and editors).
     */
    public function view(User $user, Budget $budget): bool
    {
        return (int) $budget->user_id === (int) $user->id
            || $budget->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Modifying the budget and its spending data requires owner or editor
     * access. Viewers may only view the budget.
     */
    public function update(User $user, Budget $budget): bool
    {
        return (int) $budget->user_id === (int) $user->id
            || $this->memberRole($user, $budget) === BudgetShare::ROLE_EDITOR;
    }

    /**
     * Deleting a budget stays owner-only.
     */
    public function delete(User $user, Budget $budget): bool
    {
        return (int) $budget->user_id === (int) $user->id;
    }

    /**
     * Managing members (add, remove, change roles) stays owner-only.
     */
    public function manageMembers(User $user, Budget $budget): bool
    {
        return (int) $budget->user_id === (int) $user->id;
    }

    /**
     * A shared member (viewer or editor) may leave the budget. The owner is
     * never allowed to leave through this endpoint — they manage members
     * (including removing themselves) only via manageMembers.
     */
    public function leave(User $user, Budget $budget): bool
    {
        if ((int) $budget->user_id === (int) $user->id) {
            return false;
        }

        return $budget->members()->where('user_id', $user->id)->exists();
    }

    private function memberRole(User $user, Budget $budget): ?string
    {
        return $budget->members()->where('user_id', $user->id)->value('role');
    }
}
