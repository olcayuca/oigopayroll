<?php

namespace App\Policies;

use App\Enums\FirmStatus;
use App\Enums\Permission;
use App\Enums\UserType;
use App\Models\Firm;
use App\Models\User;

/**
 * HRD Super Admins pass every check via Gate::before (AppServiceProvider).
 */
class FirmPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Firm $firm): bool
    {
        return $user->canSee($firm);
    }

    /**
     * HRD-side creation (active immediately) is reserved for super admins.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Client self-registration of a firm, which then waits for approval.
     */
    public function register(User $user): bool
    {
        return $user->is_active && $user->type === UserType::ClientUser;
    }

    public function update(User $user, Firm $firm): bool
    {
        return $user->hasPermissionOn(Permission::FirmUpdate, $firm);
    }

    public function review(User $user, Firm $firm): bool
    {
        return false;
    }

    public function manageUsers(User $user, Firm $firm): bool
    {
        return $firm->isActive() && $user->hasPermissionOn(Permission::FirmManageUsers, $firm);
    }

    public function viewDocuments(User $user, Firm $firm): bool
    {
        return $user->hasPermissionOn(Permission::FirmView, $firm);
    }

    public function manageDocuments(User $user, Firm $firm): bool
    {
        return $firm->status !== FirmStatus::Passive && $user->hasPermissionOn(Permission::FirmUpdate, $firm);
    }

    public function delete(User $user, Firm $firm): bool
    {
        return false;
    }
}
