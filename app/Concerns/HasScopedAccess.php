<?php

namespace App\Concerns;

use App\Enums\Permission;
use App\Enums\ScopeType;
use App\Enums\UserType;
use App\Models\AccessGrant;
use App\Models\Company;
use App\Models\Firm;
use App\Models\Workplace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Firm / company / workplace scoped permissions for admin portal users.
 *
 * A grant on a firm covers all of its companies and workplaces; a grant on a
 * company covers its workplaces. HRD Super Admins bypass grants entirely.
 */
trait HasScopedAccess
{
    /**
     * @var Collection<int, AccessGrant>|null
     */
    protected ?Collection $resolvedGrants = null;

    /**
     * @return HasMany<AccessGrant, $this>
     */
    public function accessGrants(): HasMany
    {
        return $this->hasMany(AccessGrant::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->type === UserType::SuperAdmin && $this->is_active;
    }

    /**
     * Determine if the user works for HRD (super admin or payroll specialist).
     */
    public function isHrdStaff(): bool
    {
        return in_array($this->type, [UserType::SuperAdmin, UserType::PayrollSpecialist], true);
    }

    /**
     * Determine if the user holds the permission on the target or any of its parents.
     */
    public function hasPermissionOn(Permission $permission, Firm|Company|Workplace $target): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->grantsCovering($target)->contains(fn (AccessGrant $grant) => $grant->allows($permission));
    }

    /**
     * Determine if the user has any grant on the target, its parents or its children.
     */
    public function canSee(Firm|Company|Workplace $target): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $this->is_active) {
            return false;
        }

        return $target::query()->visibleTo($this)->whereKey($target->getKey())->exists();
    }

    /**
     * Get the ids the user has been granted directly at the given level.
     *
     * @return list<int>
     */
    public function grantedScopeIds(ScopeType $type): array
    {
        if (! $this->is_active) {
            return [];
        }

        return array_values($this->resolvedGrants()
            ->filter(fn (AccessGrant $grant) => $grant->scope_type === $type)
            ->map(fn (AccessGrant $grant) => $grant->scope_id)
            ->all());
    }

    /**
     * Forget the cached grants after they change.
     */
    public function flushAccessCache(): void
    {
        $this->resolvedGrants = null;
    }

    /**
     * Get the grants that apply to the target: its own and its ancestors'.
     *
     * @return Collection<int, AccessGrant>
     */
    protected function grantsCovering(Firm|Company|Workplace $target): Collection
    {
        $chain = match (true) {
            $target instanceof Workplace => [
                ScopeType::Workplace->value => $target->id,
                ScopeType::Company->value => $target->company_id,
                ScopeType::Firm->value => $target->company->firm_id,
            ],
            $target instanceof Company => [
                ScopeType::Company->value => $target->id,
                ScopeType::Firm->value => $target->firm_id,
            ],
            default => [
                ScopeType::Firm->value => $target->id,
            ],
        };

        return $this->resolvedGrants()->filter(
            fn (AccessGrant $grant) => ($chain[$grant->scope_type->value] ?? null) === $grant->scope_id,
        );
    }

    /**
     * @return Collection<int, AccessGrant>
     */
    protected function resolvedGrants(): Collection
    {
        return $this->resolvedGrants ??= $this->accessGrants()->with('template')->get();
    }
}
