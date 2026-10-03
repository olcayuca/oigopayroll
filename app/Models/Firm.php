<?php

namespace App\Models;

use App\Enums\FirmSource;
use App\Enums\FirmStatus;
use App\Enums\ScopeType;
use Database\Factories\FirmFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Müşteri firma: the client account that owns one or more companies.
 *
 * Firm → Company → Workplace → (Employee)
 *
 * @property int $id
 * @property int|null $parent_firm_id
 * @property int|null $specialist_id Sorumlu bordro uzmanı; set through AssignSpecialist.
 * @property Carbon|null $specialist_assigned_at
 * @property string $name
 * @property string|null $title
 * @property string|null $tax_number
 * @property string|null $tax_office
 * @property string|null $contact_name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property FirmStatus $status
 * @property FirmSource $source
 * @property int|null $created_by
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property Carbon|null $setup_approved_at Kurulum onayı (ApproveSetup).
 * @property int|null $setup_approved_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Company> $companies
 * @property-read Collection<int, Workplace> $workplaces
 * @property-read User|null $creator
 * @property-read User|null $specialist
 * @property-read Collection<int, FirmDocument> $documents
 * @property-read Collection<int, FirmContract> $contracts
 * @property-read User|null $reviewer
 * @property-read User|null $setupApprover
 */
#[Fillable([
    'parent_firm_id', 'name', 'title', 'tax_number', 'tax_office', 'contact_name', 'phone', 'email', 'address',
    'status', 'source', 'created_by', 'reviewed_by', 'reviewed_at', 'rejection_reason',
])]
class Firm extends Model
{
    /** @use HasFactory<FirmFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return HasMany<Company, $this>
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    /**
     * @return HasManyThrough<Workplace, Company, $this>
     */
    public function workplaces(): HasManyThrough
    {
        return $this->hasManyThrough(Workplace::class, Company::class);
    }

    /**
     * The firm that opened this firm as a sub-firm.
     *
     * @return BelongsTo<Firm, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Firm::class, 'parent_firm_id');
    }

    /**
     * @return HasMany<Firm, $this>
     */
    public function subFirms(): HasMany
    {
        return $this->hasMany(Firm::class, 'parent_firm_id');
    }

    /**
     * Client users who belong to this firm.
     *
     * @return HasMany<User, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(User::class, 'firm_id');
    }

    /**
     * Links through which other firms manage this firm.
     *
     * @return HasMany<FirmLink, $this>
     */
    public function managerLinks(): HasMany
    {
        return $this->hasMany(FirmLink::class, 'managed_firm_id');
    }

    /**
     * Links through which this firm manages other firms.
     *
     * @return HasMany<FirmLink, $this>
     */
    public function managedLinks(): HasMany
    {
        return $this->hasMany(FirmLink::class, 'manager_firm_id');
    }

    /**
     * @return HasMany<FirmContract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(FirmContract::class);
    }

    /**
     * @return HasMany<FirmDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(FirmDocument::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function specialist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'specialist_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function setupApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'setup_approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Determine if the firm may use the admin portal.
     */
    public function isActive(): bool
    {
        return $this->status === FirmStatus::Active;
    }

    /**
     * Scope to firms the user can see (through a grant on the firm or anything beneath it).
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
            $query->whereIn('id', $firmIds)
                ->orWhereIn('id', Company::query()->select('firm_id')->whereIn('id', $companyIds))
                ->orWhereIn('id', Company::query()->select('firm_id')->whereIn(
                    'id',
                    Workplace::query()->select('company_id')->whereIn('id', $workplaceIds),
                ));
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
            'status' => FirmStatus::class,
            'source' => FirmSource::class,
            'reviewed_at' => 'datetime',
            'setup_approved_at' => 'datetime',
            'specialist_assigned_at' => 'datetime',
        ];
    }
}
