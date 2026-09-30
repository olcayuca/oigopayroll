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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Firm / company / workplace scoped permissions.
 *
 * A grant on a firm covers all of its companies and workplaces; a grant on a company covers
 * its workplaces. HRD Super Admins bypass grants entirely.
 *
 * Client users belong to one home firm. Their grants only count inside the home firm, or
 * inside a firm the home firm manages through an active FirmLink — and there only for the
 * permissions that link allows. Anything else is ignored, so access can never leak.
 */
trait HasScopedAccess
{
    /**
     * @var Collection<int, AccessGrant>|null
     */
    protected ?Collection $resolvedGrants = null;

    /**
     * Permissions allowed per managed firm id, for links from the home firm.
     *
     * @var array<int, list<string>>|null
     */
    protected ?array $resolvedLinkPermissions = null;

    /**
     * @return HasMany<AccessGrant, $this>
     */
    public function accessGrants(): HasMany
    {
        return $this->hasMany(AccessGrant::class);
    }

    /**
     * The firm a client user belongs to.
     *
     * @return BelongsTo<Firm, $this>
     */
    public function homeFirm(): BelongsTo
    {
        return $this->belongsTo(Firm::class, 'firm_id');
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

        return $this->grantsCovering($target)->contains(fn (AccessGrant $grant) => $this->grantAllows($grant, $permission));
    }

    /**
     * Determine if the user has any effective grant on the target, its parents or its children.
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
     * Get the ids of the user's effective grants at the given level.
     *
     * @return list<int>
     */
    public function grantedScopeIds(ScopeType $type): array
    {
        if (! $this->is_active) {
            return [];
        }

        return array_values($this->effectiveGrants()
            ->filter(fn (AccessGrant $grant) => $grant->scope_type === $type)
            ->map(fn (AccessGrant $grant) => $grant->scope_id)
            ->all());
    }

    /**
     * Firms the user's home firm manages (active links), with the permissions each link allows.
     *
     * @return array<int, list<string>>
     */
    public function managedFirmPermissions(): array
    {
        if ($this->resolvedLinkPermissions !== null) {
            return $this->resolvedLinkPermissions;
        }

        if ($this->firm_id === null) {
            return $this->resolvedLinkPermissions = [];
        }

        return $this->resolvedLinkPermissions = FirmLink::query()
            ->where('manager_firm_id', $this->firm_id)
            ->whereHas('manager', fn ($query) => $query->where('status', FirmStatus::Active))
            ->get()
            ->mapWithKeys(fn (FirmLink $link) => [$link->managed_firm_id => $link->permissions])
            ->all();
    }

    /**
     * Determine whether a grant in the given firm can apply to this user at all.
     */
    public function mayWorkInFirm(int $firmId): bool
    {
        if (! $this->type->isClient()) {
            return true;
        }

        return $firmId === $this->firm_id || array_key_exists($firmId, $this->managedFirmPermissions());
    }

    /**
     * Forget cached grants and links after they change.
     */
    public function flushAccessCache(): void
    {
        $this->resolvedGrants = null;
        $this->resolvedLinkPermissions = null;
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

        return $this->effectiveGrants()->filter(
            fn (AccessGrant $grant) => ($chain[$grant->scope_type->value] ?? null) === $grant->scope_id,
        );
    }

    /**
     * Grants that may apply: for client users only those in the home firm or a managed firm.
     *
     * @return Collection<int, AccessGrant>
     */
    protected function effectiveGrants(): Collection
    {
        return $this->resolvedGrants()->filter(fn (AccessGrant $grant) => $this->mayWorkInFirm($grant->firmId()));
    }

    protected function grantAllows(AccessGrant $grant, Permission $permission): bool
    {
        if (! $grant->allows($permission)) {
            return false;
        }

        // In a managed firm, the link caps what the grant may do.
        if ($this->type->isClient() && $grant->firmId() !== $this->firm_id) {
            return in_array($permission->value, $this->managedFirmPermissions()[$grant->firmId()] ?? [], true);
        }

        return true;
    }

    /**
     * @return Collection<int, AccessGrant>
     */
    protected function resolvedGrants(): Collection
    {
        return $this->resolvedGrants ??= AccessGrant::withFirmIds($this->accessGrants()->with('template')->get());
    }
}
