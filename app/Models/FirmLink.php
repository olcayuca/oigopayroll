<?php

namespace App\Models;

use App\Enums\Permission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Firm-to-firm access: users of the manager firm may work on the managed firm.
 *
 * A manager-firm user's effective permissions on the managed firm are the intersection of
 * their firm-level permissions on the manager firm and this link's permissions. Links are
 * not transitive, and only apply while the manager firm is active.
 *
 * @property int $id
 * @property int $manager_firm_id
 * @property int $managed_firm_id
 * @property list<string> $permissions
 * @property int|null $granted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Firm $manager
 * @property-read Firm $managed
 * @property-read User|null $granter
 */
#[Fillable(['manager_firm_id', 'managed_firm_id', 'permissions', 'granted_by'])]
class FirmLink extends Model
{
    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        static::saving(function (FirmLink $link) {
            $link->permissions = Permission::sanitize($link->permissions);
        });
    }

    /**
     * @return BelongsTo<Firm, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Firm::class, 'manager_firm_id');
    }

    /**
     * @return BelongsTo<Firm, $this>
     */
    public function managed(): BelongsTo
    {
        return $this->belongsTo(Firm::class, 'managed_firm_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function granter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function allows(Permission $permission): bool
    {
        return in_array($permission->value, $this->permissions, true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
        ];
    }
}
