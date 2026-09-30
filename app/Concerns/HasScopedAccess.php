<?php

namespace App\Concerns;

use App\Enums\FirmStatus;
use App\Enums\Permission;
use App\Enums\ScopeType;
use App\Enums\UserType;
use App\Models\AccessGrant;
use App\Models\Company;
use App\Models\Firm;
use App\Models\FirmLink;
use App\Models\Workplace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Firm / company / workplace scoped permissions for admin portal users.
 *
 * A grant on a firm covers all of its companies and workplaces; a grant on a
 * company covers its workplaces. Firm links extend a firm-level grant to the
 * firms that firm manages. HRD Super Admins bypass grants entirely.
 */
trait HasScopedAccess
{
    /**
     * @var Collection<int, AccessGrant>|null
     */
    protected ?Collection $resolvedGrants = null;

    /**
     * @var Collection<int, FirmLink>|null
     */
    protected ?Collection $resolvedLinks = null;

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

        if ($this->grantsCovering($target)->contains(fn (AccessGrant $grant) => $grant->allows($permission))) {
            return true;
        }

        // Via firm links: the link must allow it AND the user must hold it firm-wide on the manager firm.
        $firmId = self::firmIdOf($target);

        return $this->resolvedLinks()->contains(fn (FirmLink $link) => $link->managed_firm_id === $firmId
            && $link->allows($permission)
            && $this->resolvedGrants()->contains(fn (AccessGrant $grant) => $grant->scope_type === ScopeType::Firm
                && $grant->scope_id === $link->manager_firm_id
                && $grant->allows($permission)));
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
     * Get the ids the user can reach at the given level: direct grants, plus (for firms)
     * the firms managed by firms the user has a firm-level grant on.
     *
     * @return list<int>
     */
    public function grantedScopeIds(ScopeType $type): array
    {
        if (! $this->is_active) {
            return [];
        }

        $ids = $this->directScopeIds($type);

        if ($type === ScopeType::Firm) {
            $ids = [...$ids, ...$this->resolvedLinks()->pluck('managed_firm_id')->all()];
        }

        return array_values(array_unique($ids));
    }

    /**
     * Forget the cached grants and firm links after they change.
     */
    public function flushAccessCache(): void
    {
        $this->resolvedGrants = null;
        $this->resolvedLinks = null;
    }

    /**
     * @return list<int>
     */
    protected function directScopeIds(ScopeType $type): array
    {
        return array_values($this->resolvedGrants()
            ->filter(fn (AccessGrant $grant) => $grant->scope_type === $type)
            ->map(fn (AccessGrant $grant) => $grant->scope_id)
            ->all());
    }

    /**
     * Links from active firms the user holds a firm-level grant on (not transitive).
     *
     * @return Collection<int, FirmLink>
     */
    protected function resolvedLinks(): Collection
    {
        return $this->resolvedLinks ??= FirmLink::query()
            ->whereIn('manager_firm_id', $this->directScopeIds(ScopeType::Firm))
            ->whereHas('manager', fn ($query) => $query->where('status', FirmStatus::Active))
            ->get();
    }

    private static function firmIdOf(Firm|Company|Workplace $target): int
    {
        return match (true) {
            $target instanceof Workplace => $target->company->firm_id,
            $target instanceof Company => $target->firm_id,
            default => $target->id,
        };
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
