<?php

namespace App\Policies;

use App\Models\User;

trait OwnedByUserPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, $model): bool
    {
        return (int) $model->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, $model): bool
    {
        return (int) $model->user_id === (int) $user->id;
    }

    public function delete(User $user, $model): bool
    {
        return (int) $model->user_id === (int) $user->id;
    }
}
