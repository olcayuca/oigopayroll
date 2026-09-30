<?php

namespace App\Actions\Access;

use App\Enums\Permission;
use App\Models\Company;
use App\Models\Firm;
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

        $requested = Permission::sanitize([...$permissions, ...($template->permissions ?? [])]);
        $excess = array_diff($requested, $this->grantable($actor, $scope));

        if ($excess !== []) {
            $labels = implode(', ', array_map(fn ($p) => Permission::from($p)->label(), $excess));

            throw ValidationException::withMessages(['permissions' => "Sahip olmadığınız yetkiler verilemez: {$labels}."]);
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
