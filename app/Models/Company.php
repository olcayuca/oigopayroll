<?php

namespace App\Models;

use App\Enums\CompanyType;
use App\Enums\ScopeType;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Şirket. Must have at least one workplace before it can be used for payroll.
 *
 * @property int $id
 * @property int $firm_id
 * @property string $company_no
 * @property string $title
 * @property string $short_name
 * @property CompanyType $company_type
 * @property int $sector_id
 * @property string $tax_number
 * @property string $tax_office
 * @property string|null $website
 * @property string|null $kep_address
 * @property string|null $trade_registry_no
 * @property string|null $mersis_no
 * @property string|null $phone
 * @property string|null $address
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Firm $firm
 * @property-read Sector $sector
 * @property-read Collection<int, Workplace> $workplaces
 */
#[Fillable([
    'firm_id', 'company_no', 'title', 'short_name', 'company_type', 'sector_id', 'tax_number', 'tax_office',
    'website', 'kep_address', 'trade_registry_no', 'mersis_no', 'phone', 'address', 'created_by',
])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<Firm, $this>
     */
    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }

    /**
     * @return BelongsTo<Sector, $this>
     */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /**
     * @return HasMany<Workplace, $this>
     */
    public function workplaces(): HasMany
    {
        return $this->hasMany(Workplace::class);
    }

    /**
     * Scope to companies that still need their first (mandatory) workplace.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithoutWorkplaces(Builder $query): void
    {
        $query->whereDoesntHave('workplaces');
    }

    /**
     * Scope to companies the user can see.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        $firmIds = $user->grantedScopeIds(ScopeType::Firm);
        $companyIds = $user->grantedScopeIds(ScopeType::Company);
        $workplaceIds = $user->grantedScopeIds(ScopeType::Workplace);

        $query->where(function (Builder $query) use ($firmIds, $companyIds, $workplaceIds) {
            $query->whereIn('firm_id', $firmIds)
                ->orWhereIn('id', $companyIds)
                ->orWhereIn('id', Workplace::query()->select('company_id')->whereIn('id', $workplaceIds));
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
            'company_type' => CompanyType::class,
        ];
    }
}
