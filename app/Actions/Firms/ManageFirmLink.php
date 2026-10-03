<?php

namespace App\Actions\Firms;

use App\Actions\Access\DelegatedGrantGuard;
use App\Actions\Access\GrantAccess;
use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Enums\ScopeType;
use App\Models\AccessGrant;
use App\Models\Company;
use App\Models\Firm;
use App\Models\FirmLink;
use App\Models\PermissionTemplate;
use App\Models\User;
use App\Models\Workplace;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Firm-to-firm management.
 *
 * A link says "the manager firm may work on the managed firm, up to these permissions".
 * It grants nobody anything by itself: the manager firm then assigns its own users to the
 * managed firm. Removing the link removes those assignments.
 */
class ManageFirmLink
{
    public function __construct(private DelegatedGrantGuard $guard, private GrantAccess $grantAccess)
    {
        //
    }

    /**
     * Create or update a link. Set up by HRD, or ($delegated) by a member of the managed firm,
     * who may only allow permissions they hold on their own firm.
     *
     * @param  list<Permission|string>  $permissions
     */
    public function link(Firm $manager, Firm $managed, array $permissions, User $actor, bool $delegated = false): FirmLink
    {
        $permissions = Permission::sanitize($permissions);

        if ($manager->is($managed)) {
            throw ValidationException::withMessages(['manager' => 'Bir firma kendisini yönetici olarak ekleyemez.']);
        }

        if ($permissions === []) {
            throw ValidationException::withMessages(['permissions' => 'En az bir yetki seçilmelidir.']);
        }

        if ($delegated) {
            $this->ensureMemberMayManage($actor, $managed);

            $excess = array_diff($permissions, $this->guard->grantable($actor, $managed));

            if ($excess !== []) {
                $labels = implode(', ', array_map(fn ($p) => Permission::from($p)->label(), $excess));

                throw ValidationException::withMessages(['permissions' => "Sahip olmadığınız yetkiler verilemez: {$labels}."]);
            }
        }

        $link = FirmLink::updateOrCreate(
            ['manager_firm_id' => $manager->id, 'managed_firm_id' => $managed->id],
            ['permissions' => $permissions, 'granted_by' => $actor->id],
        );

        Audit::log(AuditEvent::FirmLinked, "{$manager->name} → {$managed->name} yönetim yetkisi", $managed, ['manager_firm_id' => $manager->id, 'permissions' => $permissions]);

        return $link;
    }

    /**
     * Remove a link together with the manager firm users' access to the managed firm.
     */
    public function unlink(FirmLink $link, User $actor, bool $delegated = false): void
    {
        if ($delegated) {
            $this->ensureMemberMayManage($actor, $link->managed);
        }

        DB::transaction(function () use ($link) {
            $removed = $this->assignmentsQuery($link)->delete();
            $link->delete();

            Audit::log(AuditEvent::FirmUnlinked, "{$link->manager->name} → {$link->managed->name} yönetim yetkisi kaldırıldı", $link->managed, ['manager_firm_id' => $link->manager_firm_id, 'removed_assignments' => $removed]);
        });
    }

    /**
     * A manager-firm user assigns one of their firm's users to (part of) the managed firm.
     *
     * @param  list<Permission|string>  $permissions
     */
    public function assign(
        FirmLink $link,
        User $user,
        Firm|Company|Workplace $scope,
        array $permissions,
        ?PermissionTemplate $template,
        User $actor,
    ): AccessGrant {
        $this->guard->assertCanAssignThroughLink($actor, $link, $scope, $user, Permission::sanitize($permissions), $template);

        return $this->grantAccess->handle($user, $scope, $permissions, $template, $actor);
    }

    public function unassign(FirmLink $link, AccessGrant $grant, User $actor): void
    {
        if (! $actor->isSuperAdmin()
            && ($actor->firm_id !== $link->manager_firm_id || ! $actor->can('manageUsers', $link->manager))) {
            throw ValidationException::withMessages(['link' => 'Bu firmadaki atamaları yönetme yetkiniz yok.']);
        }

        if (! $this->assignmentsQuery($link)->whereKey($grant->id)->exists()) {
            throw ValidationException::withMessages(['link' => 'Atama bulunamadı.']);
        }

        $grant->delete();
        $grant->user->flushAccessCache();

        Audit::log(AuditEvent::AccessRevoked, "{$grant->user->name} kullanıcısının {$link->managed->name} ataması kaldırıldı", $grant->user, scope: $link->managed);
    }

    /**
     * Grants that users of the manager firm hold inside the managed firm.
     *
     * @return Builder<AccessGrant>
     */
    public function assignmentsQuery(FirmLink $link): Builder
    {
        $companyIds = Company::withTrashed()->where('firm_id', $link->managed_firm_id)->pluck('id');
        $workplaceIds = Workplace::withTrashed()->whereIn('company_id', $companyIds)->pluck('id');

        return AccessGrant::query()
            ->whereIn('user_id', User::where('firm_id', $link->manager_firm_id)->select('id'))
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q->where('scope_type', ScopeType::Firm)->where('scope_id', $link->managed_firm_id))
                ->orWhere(fn ($q) => $q->where('scope_type', ScopeType::Company)->whereIn('scope_id', $companyIds))
                ->orWhere(fn ($q) => $q->where('scope_type', ScopeType::Workplace)->whereIn('scope_id', $workplaceIds)));
    }

    /**
     * Only the managed firm's own members (or HRD) decide who may manage it.
     */
    private function ensureMemberMayManage(User $actor, Firm $managed): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        if ($actor->firm_id !== $managed->id || ! $actor->can('manageUsers', $managed)) {
            throw ValidationException::withMessages(['manager' => 'Firma erişimlerini yalnızca firmanın kendi yetkilileri yönetebilir.']);
        }
    }
}
