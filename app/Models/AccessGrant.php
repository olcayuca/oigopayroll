<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\ScopeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Grants a user permissions on a firm, company or workplace (and everything beneath it).
 *
 * Effective permissions = the template's permissions (if any) + the explicit list.
 *
 * @property int $id
 * @property int $user_id
 * @property ScopeType $scope_type
 * @property int $scope_id
 * @property int|null $permission_template_id
 * @property list<string>|null $permissions
 * @property int|null $granted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read PermissionTemplate|null $template
 */
#[Fillable(['user_id', 'scope_type', 'scope_id', 'permission_template_id', 'permissions', 'granted_by'])]
class AccessGrant extends Model
{
    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        static::saving(function (AccessGrant $grant) {
            $grant->permissions = Permission::sanitize($grant->permissions ?? []);
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<PermissionTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(PermissionTemplate::class, 'permission_template_id');
    }

    /**
     * Get the combined permission values of this grant.
     *
     * @return list<string>
     */
    public function effectivePermissions(): array
    {
        return Permission::sanitize([
            ...($this->template->permissions ?? []),
            ...($this->permissions ?? []),
        ]);
    }

    /**
     * Determine if this grant includes the given permission.
     */
    public function allows(Permission $permission): bool
    {
        return in_array($permission->value, $this->effectivePermissions(), true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope_type' => ScopeType::class,
            'permissions' => 'array',
        ];
    }
}
