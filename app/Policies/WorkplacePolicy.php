<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Company;
use App\Models\Firm;
use App\Models\User;
use App\Models\Workplace;

/**
 * HRD Super Admins pass every check via Gate::before (AppServiceProvider).
 */
class WorkplacePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Workplace $workplace): bool
    {
        return $user->canSee($workplace);
    }

    /**
     * Usage: $user->can('create', [Workplace::class, $company])
     */
    public function create(User $user, Company $company): bool
    {
        return $company->firm->isActive() && $user->hasPermissionOn(Permission::WorkplaceCreate, $company);
    }

    /**
     * Usage: $user->can('import', [Workplace::class, $firm])
     */
    public function import(User $user, Firm $firm): bool
    {
        return $firm->isActive() && $user->hasPermissionOn(Permission::WorkplaceImport, $firm);
    }

    public function update(User $user, Workplace $workplace): bool
    {
        return $workplace->company->firm->isActive() && $user->hasPermissionOn(Permission::WorkplaceUpdate, $workplace);
    }

    public function delete(User $user, Workplace $workplace): bool
    {
        return $workplace->company->firm->isActive() && $user->hasPermissionOn(Permission::WorkplaceDelete, $workplace);
    }

    public function viewCredentials(User $user, Workplace $workplace): bool
    {
        return $user->hasPermissionOn(Permission::WorkplaceViewCredentials, $workplace);
    }
}
