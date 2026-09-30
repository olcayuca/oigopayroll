<?php

namespace App\Actions\Access;

use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Enums\ScopeType;
use App\Models\AccessGrant;
use App\Models\Company;
use App\Models\Firm;
use App\Models\PermissionTemplate;
use App\Models\User;
use App\Models\Workplace;
use App\Support\Audit;
use Illuminate\Validation\ValidationException;

/**
 * Give (or replace) a user's permissions on a firm, company or workplace.
 */
class GrantAccess
{
    /**
     * @param  list<Permission|string>  $permissions
     */
    public function handle(
        User $user,
        Firm|Company|Workplace $scope,
        array $permissions = [],
        ?PermissionTemplate $template = null,
        ?User $grantedBy = null,
    ): AccessGrant {
        $permissions = Permission::sanitize($permissions);

        if ($permissions === [] && $template === null) {
            throw ValidationException::withMessages(['permissions' => 'En az bir yetki veya bir yetki şablonu seçilmelidir.']);
        }

        $this->ensureNoLeak($user, $scope);

        $grant = AccessGrant::updateOrCreate(
            [
                'user_id' => $user->id,
                'scope_type' => self::scopeType($scope),
                'scope_id' => $scope->getKey(),
            ],
            [
                'permissions' => $permissions,
                'permission_template_id' => $template?->id,
                'granted_by' => $grantedBy?->id,
            ],
        );

        $user->flushAccessCache();

        Audit::log(AuditEvent::AccessGranted, "{$user->name}: {$grant->scopeLabel()} ({$grant->scope_type->label()})", $user, [
            'scope_type' => $grant->scope_type->value,
            'scope_id' => $grant->scope_id,
            'permissions' => $grant->effectivePermissions(),
        ]);

        return $grant;
    }

    /**
     * Remove a user's grant on the scope.
     */
    public function revoke(User $user, Firm|Company|Workplace $scope): void
    {
        AccessGrant::query()
            ->where('user_id', $user->id)
            ->where('scope_type', self::scopeType($scope))
            ->where('scope_id', $scope->getKey())
            ->delete();

        Audit::log(AuditEvent::AccessRevoked, "{$user->name} kullanıcısının yetkisi kaldırıldı", $user, [
            'scope_type' => self::scopeType($scope)->value,
            'scope_id' => $scope->getKey(),
        ]);

        $user->flushAccessCache();
    }

    /**
     * A client user belongs to one firm: the first firm they get access to becomes their home firm,
     * and afterwards they can only be given access inside it or inside firms it manages.
     */
    private function ensureNoLeak(User $user, Firm|Company|Workplace $scope): void
    {
        if (! $user->type->isClient()) {
            return;
        }

        $firm = DelegatedGrantGuard::firmOf($scope);

        if ($user->firm_id === null) {
            $user->forceFill(['firm_id' => $firm->id])->save();
            $user->flushAccessCache();

            return;
        }

        $user->flushAccessCache();

        if (! $user->mayWorkInFirm($firm->id)) {
            $home = $user->homeFirm->name ?? 'başka bir firma';

            throw ValidationException::withMessages([
                'email' => "{$user->name} \"{$home}\" firmasına ait; yalnızca kendi firmasında veya firmasının yönettiği firmalarda yetkilendirilebilir.",
            ]);
        }
    }

    private static function scopeType(Firm|Company|Workplace $scope): ScopeType
    {
        return match (true) {
            $scope instanceof Firm => ScopeType::Firm,
            $scope instanceof Company => ScopeType::Company,
            default => ScopeType::Workplace,
        };
    }
}
