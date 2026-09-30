<?php

namespace App\Models;

use App\Enums\Permission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Optional reusable bundle of permissions ("yetki şablonu").
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property list<string> $permissions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description', 'permissions'])]
class PermissionTemplate extends Model
{
    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        static::saving(function (PermissionTemplate $template) {
            $template->permissions = Permission::sanitize($template->permissions);
        });
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
