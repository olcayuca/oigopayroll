<?php

namespace App\Actions\Access;

use App\Enums\Permission;
use App\Enums\ScopeType;
use App\Models\AccessGrant;
use App\Models\Company;
use App\Models\Firm;
use App\Models\PermissionTemplate;
use App\Models\User;
use App\Models\Workplace;
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

        $user->flushAccessCache();
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
