<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Company;
use App\Models\Firm;
use App\Models\User;

/**
 * HRD Super Admins pass every check via Gate::before (AppServiceProvider).
 */
class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Company $company): bool
    {
        return $user->canSee($company);
    }

    /**
     * Usage: $user->can('create', [Company::class, $firm])
     */
    public function create(User $user, Firm $firm): bool
    {
        return $firm->isActive() && $user->hasPermissionOn(Permission::CompanyCreate, $firm);
    }

    /**
     * Usage: $user->can('import', [Company::class, $firm])
     */
    public function import(User $user, Firm $firm): bool
    {
        return $firm->isActive() && $user->hasPermissionOn(Permission::CompanyImport, $firm);
    }

    public function update(User $user, Company $company): bool
    {
        return $company->firm->isActive() && $user->hasPermissionOn(Permission::CompanyUpdate, $company);
    }

    public function delete(User $user, Company $company): bool
    {
        return $company->firm->isActive() && $user->hasPermissionOn(Permission::CompanyDelete, $company);
    }
}
