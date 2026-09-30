<?php

namespace App\Models;

use App\Enums\HazardClass;
use App\Enums\RegistrationType;
use App\Enums\ScopeType;
use App\Enums\WorkplaceKind;
use App\Enums\WorkplaceType;
use Database\Factories\WorkplaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * İşyeri. Belongs to a company; employees and payroll attach here in later phases.
 *
 * Credential columns are encrypted at rest and hidden from serialization; read them
 * through RevealWorkplaceCredential so every access is authorized and logged.
 *
 * @property int $id
 * @property int $company_id
 * @property string $workplace_no
 * @property string $branch_name
 * @property WorkplaceType $workplace_type
 * @property WorkplaceKind $workplace_kind
 * @property string $title
 * @property RegistrationType|null $registration_type
 * @property string $tax_number
 * @property string $tax_office
 * @property string|null $mersis_no
 * @property int|null $risk_class_id
 * @property HazardClass $hazard_class
 * @property int|null $labor_sector_id
 * @property int|null $province_id
 * @property string|null $province_name
 * @property int|null $district_id
 * @property string|null $district_name
 * @property string|null $neighborhood
 * @property string|null $street
 * @property string|null $outer_door_no
 * @property string|null $inner_door_no
 * @property string|null $postal_code
 * @property string $address
 * @property string|null $phone
 * @property string|null $mobile_phone
 * @property string|null $email
 * @property string|null $kep_address
 * @property string|null $e_signature_officer
 * @property string|null $sgk_registry_no
 * @property string|null $sgk_directorate
 * @property string $sgk_officer_name
 * @property string $sgk_workplace_code
 * @property string $ebildirge_officer_name
 * @property Carbon $opening_date
 * @property Carbon|null $closing_date
 * @property string|null $mahiyet_code
 * @property string|null $mahiyet_name
 * @property string|null $iskur_user_name
 * @property string|null $iskur_registry_no
 * @property string|null $tuik_user_full_name
 * @property string|null $tuik_username
 * @property string|null $tax_office_user_code
 * @property string $sgk_declaration_username
 * @property string $sgk_workplace_password
 * @property string $sgk_system_password
 * @property string|null $iskur_user_code
 * @property string|null $iskur_password
 * @property string|null $tuik_password
 * @property string|null $ebeyanname_password
 * @property bool $has_union
 * @property string|null $union_name
 * @property Carbon|null $cba_start_date
 * @property Carbon|null $cba_end_date
 * @property Carbon|null $cba_signed_date
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Company $company
 * @property-read Province|null $province
 * @property-read District|null $district
 * @property-read RiskClass|null $riskClass
 * @property-read LaborSector|null $laborSector
 */
#[Fillable([
    'company_id', 'workplace_no', 'branch_name', 'workplace_type', 'workplace_kind', 'title', 'registration_type',
    'tax_number', 'tax_office', 'mersis_no', 'risk_class_id', 'hazard_class', 'labor_sector_id',
    'province_id', 'province_name', 'district_id', 'district_name', 'neighborhood', 'street', 'outer_door_no',
    'inner_door_no', 'postal_code', 'address', 'phone', 'mobile_phone', 'email', 'kep_address', 'e_signature_officer',
    'sgk_registry_no', 'sgk_directorate', 'sgk_officer_name', 'sgk_workplace_code', 'ebildirge_officer_name',
    'opening_date', 'closing_date', 'mahiyet_code', 'mahiyet_name', 'iskur_user_name', 'iskur_registry_no',
    'tuik_user_full_name', 'tuik_username', 'tax_office_user_code', 'sgk_declaration_username',
    'sgk_workplace_password', 'sgk_system_password', 'iskur_user_code', 'iskur_password', 'tuik_password',
    'ebeyanname_password', 'has_union', 'union_name', 'cba_start_date', 'cba_end_date', 'cba_signed_date', 'created_by',
])]
#[Hidden(Workplace::SECRET_FIELDS)]
class Workplace extends Model
{
    /** @use HasFactory<WorkplaceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Encrypted credential columns.
     */
    public const SECRET_FIELDS = [
        'sgk_declaration_username',
        'sgk_workplace_password',
        'sgk_system_password',
        'iskur_user_code',
        'iskur_password',
        'tuik_password',
        'ebeyanname_password',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * @return BelongsTo<RiskClass, $this>
     */
    public function riskClass(): BelongsTo
    {
        return $this->belongsTo(RiskClass::class);
    }

    /**
     * @return BelongsTo<LaborSector, $this>
     */
    public function laborSector(): BelongsTo
    {
        return $this->belongsTo(LaborSector::class);
    }

    /**
     * Get the province name, whether chosen from the list or typed manually.
     */
    public function provinceLabel(): ?string
    {
        return $this->province->name ?? $this->province_name;
    }

    /**
     * Get the district name, whether chosen from the list or typed manually.
     */
    public function districtLabel(): ?string
    {
        return $this->district->name ?? $this->district_name;
    }

    /**
     * Scope to workplaces the user can see.
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
            $query->whereIn('id', $workplaceIds)
                ->orWhereIn('company_id', $companyIds)
                ->orWhereIn('company_id', Company::query()->select('id')->whereIn('firm_id', $firmIds));
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
            'workplace_type' => WorkplaceType::class,
            'workplace_kind' => WorkplaceKind::class,
            'registration_type' => RegistrationType::class,
            'hazard_class' => HazardClass::class,
            'opening_date' => 'date',
            'closing_date' => 'date',
            'cba_start_date' => 'date',
            'cba_end_date' => 'date',
            'cba_signed_date' => 'date',
            'has_union' => 'boolean',
            ...array_fill_keys(self::SECRET_FIELDS, 'encrypted'),
        ];
    }
}
