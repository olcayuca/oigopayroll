<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\ScopeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
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
     * Firm the scope belongs to (0 if the scope no longer exists). Filled in bulk by withFirmIds().
     */
    public ?int $resolvedFirmId = null;

    /**
     * Resolve the firm of each grant's scope with two queries instead of one per grant.
     *
     * @param  Collection<int, AccessGrant>  $grants
     * @return Collection<int, AccessGrant>
     */
    public static function withFirmIds(Collection $grants): Collection
    {
        $idsOf = fn (ScopeType $type) => $grants->where('scope_type', $type)->pluck('scope_id')->all();

        $companyFirms = Company::withTrashed()->whereIn('id', $idsOf(ScopeType::Company))->pluck('firm_id', 'id');
        $workplaceFirms = Workplace::withTrashed()
            ->join('companies', 'companies.id', '=', 'workplaces.company_id')
            ->whereIn('workplaces.id', $idsOf(ScopeType::Workplace))
            ->pluck('companies.firm_id', 'workplaces.id');

        foreach ($grants as $grant) {
            $grant->resolvedFirmId = (int) match ($grant->scope_type) {
                ScopeType::Firm => $grant->scope_id,
                ScopeType::Company => $companyFirms[$grant->scope_id] ?? 0,
                ScopeType::Workplace => $workplaceFirms[$grant->scope_id] ?? 0,
            };
        }

        return $grants;
    }

    public function firmId(): int
    {
        return $this->resolvedFirmId ??= (int) match ($this->scope_type) {
            ScopeType::Firm => $this->scope_id,
            ScopeType::Company => Company::withTrashed()->whereKey($this->scope_id)->value('firm_id') ?? 0,
            ScopeType::Workplace => Workplace::withTrashed()
                ->join('companies', 'companies.id', '=', 'workplaces.company_id')
                ->where('workplaces.id', $this->scope_id)
                ->value('companies.firm_id') ?? 0,
        };
    }

    /**
     * Get the firm, company or workplace this grant applies to.
     */
    public function scopeModel(): Firm|Company|Workplace|null
    {
        return match ($this->scope_type) {
            ScopeType::Firm => Firm::find($this->scope_id),
            ScopeType::Company => Company::with('firm')->find($this->scope_id),
            ScopeType::Workplace => Workplace::with('company.firm')->find($this->scope_id),
        };
    }

    /**
     * Human readable scope, e.g. "Demo Holding › Demo Teknoloji A.Ş. › Merkez".
     */
    public function scopeLabel(): string
    {
        $model = $this->scopeModel();

        return match (true) {
            $model instanceof Firm => $model->name,
            $model instanceof Company => $model->firm->name.' › '.$model->title,
            $model instanceof Workplace => $model->company->firm->name.' › '.$model->company->title.' › '.$model->branch_name,
            default => '(silinmiş kayıt)',
        };
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
