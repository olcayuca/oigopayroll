<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\ScopeType;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Personel. Belongs to a company (Firma) and a workplace (SGK firma + şube) of the same firm.
 *
 * @property int $id
 * @property int $firm_id
 * @property int $company_id
 * @property int $workplace_id
 * @property string $status
 * @property Carbon|null $termination_date
 * @property string|null $termination_code
 * @property string|null $termination_note
 * @property string $registry_no
 * @property string $tckn
 * @property string $first_name
 * @property string $last_name
 * @property string|null $second_last_name
 * @property Carbon $birth_date
 * @property Carbon $hire_date
 * @property string $wage
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'firm_id', 'company_id', 'workplace_id', 'status', 'termination_date', 'termination_code', 'termination_note',
    'registry_no', 'tckn', 'tckn_hash', 'first_name', 'last_name', 'second_last_name', 'work_email', 'personal_email',
    'mobile_phone', 'work_phone', 'address', 'province', 'district',
    'birth_date', 'gender', 'marital_status', 'education', 'graduation_field', 'military_status',
    'hire_date', 'seniority_date', 'leave_base_date', 'collar', 'job_family_id', 'unit_id', 'upper_unit_id', 'duty_type',
    'title_id', 'position_id', 'level_id', 'leave_manager_registry_no', 'functional_manager_registry_no',
    'occupation_code', 'insurance_branch', 'sgk_status', 'employment_type', 'duty_code', 'sgk_document_type',
    'bank_name', 'bank_branch', 'iban', 'account_no',
    'wage_period', 'currency', 'wage_type', 'wage', 'is_minimum_wage', 'minimum_wage_exemption', 'bes_rate',
    'cumulative_tax_base', 'tax_exemption_start_month', 'previous_sgk_base_1', 'previous_sgk_base_2', 'rnd_rate',
    'disability_degree', 'disability_tax_relief', 'disability_end_date', 'cost_group_id', 'cost_group_rate',
    'work_model', 'contract_type', 'is_shift_worker', 'shift_start', 'shift_end', 'weekly_rest', 'remaining_leave_days',
    'created_by',
])]
#[Hidden(['tckn', 'tckn_hash', 'iban', 'account_no'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, SoftDeletes;

    public const ACTIVE = 'active';

    public const PASSIVE = 'passive';

    public const LEFT = 'left';

    public const STATUSES = [self::ACTIVE => 'Aktif', self::PASSIVE => 'Pasif', self::LEFT => 'İşten ayrıldı'];

    /**
     * Encrypted columns.
     */
    public const SECRET_FIELDS = ['tckn', 'iban', 'account_no'];

    /**
     * Columns of the setup file marked required (docs/KURULUM_DOSYASI.md §2), grouped like the form tabs.
     */
    public const REQUIRED_FIELDS = [
        'kimlik' => ['registry_no', 'tckn', 'first_name', 'last_name', 'personal_email', 'mobile_phone'],
        'kisisel' => ['birth_date', 'gender'],
        'istihdam' => ['company_id', 'workplace_id', 'upper_unit_id', 'title_id', 'position_id', 'leave_manager_registry_no',
            'hire_date', 'seniority_date', 'leave_base_date'],
        'sgk' => ['occupation_code', 'insurance_branch', 'sgk_status', 'employment_type', 'duty_code', 'sgk_document_type'],
        'ucret' => ['wage_period', 'currency', 'wage_type', 'wage', 'is_minimum_wage', 'minimum_wage_exemption', 'bes_rate',
            'cumulative_tax_base', 'tax_exemption_start_month', 'previous_sgk_base_1', 'previous_sgk_base_2'],
        'banka' => ['bank_name', 'bank_branch', 'iban', 'account_no'],
        'engel' => [],
        'calisma' => ['work_model', 'contract_type', 'is_shift_worker', 'shift_start', 'shift_end', 'weekly_rest', 'remaining_leave_days'],
    ];

    /**
     * Hash used for TCKN uniqueness and lookups without decrypting.
     */
    public static function hashTckn(string $tckn): string
    {
        return hash_hmac('sha256', trim($tckn), (string) config('app.key'));
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1));
    }

    /**
     * TCKN with the middle digits hidden: 123••••••01.
     */
    public function maskedTckn(): string
    {
        $tckn = (string) $this->tckn;

        return strlen($tckn) === 11 ? substr($tckn, 0, 3).'••••••'.substr($tckn, -2) : '•••••••••••';
    }

    /**
     * Missing required fields (blank) — records imported as updates may still miss some.
     *
     * @return list<string>
     */
    public function missingRequiredFields(): array
    {
        $attributes = $this->getAttributes();

        return array_values(array_filter(
            array_merge(...array_values(self::REQUIRED_FIELDS)),
            fn (string $field) => ($attributes[$field] ?? null) === null || $attributes[$field] === '',
        ));
    }

    /**
     * Records with at least one blank required field.
     *
     * @param  Builder<self>  $query
     */
    public function scopeIncomplete(Builder $query): void
    {
        $casts = (new self)->getCasts();

        $query->where(function (Builder $query) use ($casts) {
            foreach (array_merge(...array_values(self::REQUIRED_FIELDS)) as $field) {
                $query->orWhereNull($field);

                // '' only exists in text columns (MySQL strict mode rejects it for dates / times).
                $isText = ! str_ends_with($field, '_id') && ! in_array($field, ['shift_start', 'shift_end'], true)
                    && (! isset($casts[$field]) || $casts[$field] === 'encrypted');
                if ($isText) {
                    $query->orWhere($field, '');
                }
            }
        });
    }

    public function completionPercent(): int
    {
        $total = count(array_merge(...array_values(self::REQUIRED_FIELDS)));

        return (int) floor(($total - count($this->missingRequiredFields())) * 100 / $total);
    }

    /**
     * @return BelongsTo<Firm, $this>
     */
    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Workplace, $this>
     */
    public function workplace(): BelongsTo
    {
        return $this->belongsTo(Workplace::class);
    }

    /**
     * @return BelongsTo<Definition, $this>
     */
    public function upperUnit(): BelongsTo
    {
        return $this->belongsTo(Definition::class, 'upper_unit_id');
    }

    /**
     * @return BelongsTo<Definition, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Definition::class, 'unit_id');
    }

    /**
     * @return BelongsTo<Definition, $this>
     */
    public function jobFamily(): BelongsTo
    {
        return $this->belongsTo(Definition::class, 'job_family_id');
    }

    /**
     * @return BelongsTo<Definition, $this>
     */
    public function title(): BelongsTo
    {
        return $this->belongsTo(Definition::class, 'title_id');
    }

    /**
     * @return BelongsTo<Definition, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Definition::class, 'position_id');
    }

    /**
     * @return BelongsTo<Definition, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Definition::class, 'level_id');
    }

    /**
     * @return BelongsTo<Definition, $this>
     */
    public function costGroup(): BelongsTo
    {
        return $this->belongsTo(Definition::class, 'cost_group_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Personnel the user can see: through a firm, company or workplace grant.
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
            $query->whereIn('workplace_id', $workplaceIds)
                ->orWhereIn('company_id', $companyIds)
                ->orWhereIn('firm_id', $firmIds);
        });
    }

    /**
     * Personnel of a firm the user may view: employee.view on the workplace.
     *
     * @param  Builder<self>  $query
     */
    public function scopeViewableBy(Builder $query, User $user, Firm $firm): void
    {
        $workplaceIds = Workplace::visibleTo($user)
            ->whereHas('company', fn ($query) => $query->where('firm_id', $firm->id))
            ->with('company')
            ->get()
            ->filter(fn (Workplace $workplace) => $user->hasPermissionOn(Permission::EmployeeView, $workplace))
            ->map(fn (Workplace $workplace): int => $workplace->id)
            ->all();

        $query->where('firm_id', $firm->id)->whereIn('workplace_id', $workplaceIds);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tckn' => 'encrypted',
            'iban' => 'encrypted',
            'account_no' => 'encrypted',
            'birth_date' => 'date',
            'hire_date' => 'date',
            'seniority_date' => 'date',
            'leave_base_date' => 'date',
            'disability_end_date' => 'date',
            'termination_date' => 'date',
            'wage' => 'decimal:2',
            'bes_rate' => 'decimal:2',
            'cumulative_tax_base' => 'decimal:2',
            'previous_sgk_base_1' => 'decimal:2',
            'previous_sgk_base_2' => 'decimal:2',
            'cost_group_rate' => 'decimal:2',
            'remaining_leave_days' => 'decimal:2',
            'tax_exemption_start_month' => 'integer',
            'is_minimum_wage' => 'boolean',
            'minimum_wage_exemption' => 'boolean',
            'disability_tax_relief' => 'boolean',
            'is_shift_worker' => 'boolean',
        ];
    }
}
