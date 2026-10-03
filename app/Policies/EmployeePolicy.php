<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Firm;
use App\Models\User;
use App\Models\Workplace;

/**
 * Personnel rights are checked on the employee's workplace (a firm, company or workplace grant covers it).
 * HRD Super Admins pass every check via Gate::before (AppServiceProvider).
 */
class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->hasPermissionOn(Permission::EmployeeView, $employee->workplace);
    }

    /**
     * Usage: $user->can('create', [Employee::class, $firm]) — any workplace of the firm qualifies.
     */
    public function create(User $user, Firm $firm): bool
    {
        return $firm->isActive() && $this->creatableWorkplaces($user, $firm) !== [];
    }

    /**
     * Usage: $user->can('createIn', [Employee::class, $workplace])
     */
    public function createIn(User $user, Workplace $workplace): bool
    {
        return $workplace->company->firm->isActive() && $user->hasPermissionOn(Permission::EmployeeCreate, $workplace);
    }

    /**
     * Usage: $user->can('import', [Employee::class, $firm])
     */
    public function import(User $user, Firm $firm): bool
    {
        return $firm->isActive() && $user->hasPermissionOn(Permission::EmployeeImport, $firm);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $employee->firm->isActive() && $user->hasPermissionOn(Permission::EmployeeUpdate, $employee->workplace);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $employee->firm->isActive() && $user->hasPermissionOn(Permission::EmployeeDelete, $employee->workplace);
    }

    /**
     * Restore from the trash: the workplace and company must not be deleted themselves.
     */
    public function restore(User $user, Employee $employee): bool
    {
        $workplace = Workplace::withTrashed()->find($employee->workplace_id);

        return $workplace !== null && ! $workplace->trashed()
            && ! Company::onlyTrashed()->whereKey([$employee->company_id, $workplace->company_id])->exists()
            && $employee->firm->isActive() && $user->hasPermissionOn(Permission::EmployeeDelete, $workplace);
    }

    /**
     * Workplace ids of the firm where the user may add personnel.
     *
     * @return list<int>
     */
    public function creatableWorkplaces(User $user, Firm $firm): array
    {
        return array_values(Workplace::visibleTo($user)
            ->whereHas('company', fn ($query) => $query->where('firm_id', $firm->id))
            ->with('company.firm')
            ->get()
            ->filter(fn (Workplace $workplace) => $user->hasPermissionOn(Permission::EmployeeCreate, $workplace))
            ->map(fn (Workplace $workplace): int => $workplace->id)
            ->all());
    }
}
