<?php

namespace App\Policies;

use App\Models\User;

/**
 * User administration is reserved for HRD Super Admins (granted via Gate::before).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, User $model): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $model): bool
    {
        return false;
    }
}
