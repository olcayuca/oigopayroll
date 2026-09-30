<?php

namespace App\Actions\Access;

use App\Enums\Permission;
use App\Models\Company;
use App\Models\Firm;
use App\Models\FirmLink;
use App\Models\PermissionTemplate;
use App\Models\User;
use App\Models\Workplace;
use Illuminate\Validation\ValidationException;

/**
 * Rules for users who manage their own firm's users from the panel (Permission::FirmManageUsers):
 * they may only hand out permissions they hold themselves, only to client users,
 * only inside their firm, and never change their own access.
 */
class DelegatedGrantGuard
{
    /**
     * Permissions the actor may grant on the scope.
     *
     * @return list<string>
     */
    public function grantable(User $actor, Firm|Company|Workplace $scope): array
    {
        if ($actor->isSuperAdmin()) {
            return array_map(fn (Permission $p) => $p->value, Permission::cases());
        }

        return array_values(array_map(
            fn (Permission $p) => $p->value,
            array_filter(Permission::cases(), fn (Permission $p) => $actor->hasPermissionOn($p, $scope)),
        ));
    }

    /**
     * @param  list<string>  $permissions
     */
    public function assertCanGrant(
        User $actor,
        Firm $firm,
        Firm|Company|Workplace $scope,
        User $target,
        array $permissions,
        ?PermissionTemplate $template,
    ): void {
        if (! $actor->can('manageUsers', $firm)) {
            throw ValidationException::withMessages(['permissions' => 'Bu firmada kullanıcı yönetme yetkiniz yok.']);
        }

        if (self::firmOf($scope)->isNot($firm)) {
            throw ValidationException::withMessages(['scope' => 'Yetki yalnızca bu firmanın kayıtlarında verilebilir.']);
        }

        if ($actor->isSuperAdmin()) {
            return;
        }

        if ($target->is($actor)) {
            throw ValidationException::withMessages(['email' => 'Kendi yetkilerinizi değiştiremezsiniz.']);
        }

        if (! $target->type->isClient()) {
            throw ValidationException::withMessages(['email' => 'HRD personelinin yetkileri yalnızca HRD tarafından yönetilir.']);
        }

        // A firm manages only its own members here (users of managing firms are assigned by that firm).
        if ($target->firm_id !== null && $target->firm_id !== $firm->id) {
            throw ValidationException::withMessages(['email' => 'Bu kullanıcı başka bir firmaya ait; buradan yetkilendirilemez.']);
        }

        $this->ensureWithin(Permission::sanitize([...$permissions, ...($template->permissions ?? [])]), $this->grantable($actor, $scope));
    }

    /**
     * Permissions a manager-firm user may hand to members of their own firm on the managed firm.
     *
     * @return list<string>
     */
    public function grantableThroughLink(User $actor, FirmLink $link): array
    {
        if ($actor->isSuperAdmin()) {
            return $link->permissions;
        }

        return array_values(array_intersect($link->permissions, $this->grantable($actor, $link->manager)));
    }

    /**
     * A managing firm assigns one of its own users to a firm it manages.
     *
     * @param  list<string>  $permissions
     */
    public function assertCanAssignThroughLink(
        User $actor,
        FirmLink $link,
        Firm|Company|Workplace $scope,
        User $target,
        array $permissions,
        ?PermissionTemplate $template,
    ): void {
        if (! $link->manager->isActive()) {
            throw ValidationException::withMessages(['link' => 'Yönetici firma aktif değil.']);
        }

        if (self::firmOf($scope)->isNot($link->managed)) {
            throw ValidationException::withMessages(['scope' => 'Yetki yalnızca yönetilen firmanın kayıtlarında verilebilir.']);
        }

        if (! $actor->isSuperAdmin()
            && ($actor->firm_id !== $link->manager_firm_id || ! $actor->can('manageUsers', $link->manager))) {
            throw ValidationException::withMessages(['link' => 'Bu firmaya kullanıcı atama yetkiniz yok.']);
        }

        if (! $target->type->isClient() || $target->firm_id !== $link->manager_firm_id) {
            throw ValidationException::withMessages(['user' => 'Yalnızca yönetici firmaya ait kullanıcılar atanabilir.']);
        }

        $this->ensureWithin(
            Permission::sanitize([...$permissions, ...($template->permissions ?? [])]),
            $this->grantableThroughLink($actor, $link),
        );
    }

    /**
     * @param  list<string>  $requested
     * @param  list<string>  $allowed
     */
    private function ensureWithin(array $requested, array $allowed): void
    {
        $excess = array_diff($requested, $allowed);

        if ($excess !== []) {
            $labels = implode(', ', array_map(fn ($p) => Permission::from($p)->label(), $excess));

            throw ValidationException::withMessages(['permissions' => "Bu yetkiler verilemez: {$labels}."]);
        }
    }

    public static function firmOf(Firm|Company|Workplace $scope): Firm
    {
        return match (true) {
            $scope instanceof Firm => $scope,
            $scope instanceof Company => $scope->firm,
            default => $scope->company->firm,
        };
    }
}
