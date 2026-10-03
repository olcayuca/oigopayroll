<?php

namespace App\Models;

use App\Enums\AuditEvent;
use App\Enums\Permission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Append-only activity / security record. Write through App\Support\Audit.
 *
 * @property int $id
 * @property int|null $user_id
 * @property AuditEvent $event
 * @property string $description
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $properties
 * @property string|null $portal
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 * @property-read User|null $user
 * @property-read Model|null $subject
 */
#[Fillable(['user_id', 'firm_id', 'company_id', 'workplace_id', 'event', 'description', 'subject_type', 'subject_id', 'properties', 'portal', 'ip_address', 'user_agent'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Workplace, $this>
     */
    public function workplace(): BelongsTo
    {
        return $this->belongsTo(Workplace::class)->withTrashed();
    }

    /**
     * Field-level changes ("old → new") recorded with the event.
     *
     * @return list<array{field: string, label: string, old: string, new: string}>
     */
    public function changes(): array
    {
        $changes = $this->properties['changes'] ?? [];
        $list = [];

        foreach (is_array($changes) ? $changes : [] as $change) {
            if (is_array($change)) {
                $list[] = [
                    'field' => (string) ($change['field'] ?? ''),
                    'label' => (string) ($change['label'] ?? $change['field'] ?? ''),
                    'old' => (string) ($change['old'] ?? '—'),
                    'new' => (string) ($change['new'] ?? '—'),
                ];
            }
        }

        return $list;
    }

    /**
     * Panel İşlem Geçmişi: records of the firm the user may see.
     *
     * - firm.view_audit on the firm: everything of the firm, plus the sign-ins of the firm's own users;
     * - on a company / workplace only: records of those companies / workplaces.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleInFirm(Builder $query, User $user, Firm $firm): void
    {
        $reach = self::reach($user, $firm);

        if ($reach['firm']) {
            $query->where(fn (Builder $query) => $query
                ->where('firm_id', $firm->id)
                ->orWhere(fn (Builder $query) => $query
                    ->whereNull('firm_id')
                    ->where('event', 'like', 'auth.%')
                    ->whereIn('user_id', User::query()->where('firm_id', $firm->id)->select('id'))));

            return;
        }

        $query->where('firm_id', $firm->id)->where(fn (Builder $query) => $query
            ->whereIn('company_id', $reach['companies'])
            ->orWhereIn('workplace_id', $reach['workplaces'])
            ->orWhereIn('workplace_id', Workplace::withTrashed()->whereIn('company_id', $reach['companies'])->select('id')));
    }

    /**
     * Where the user may read the firm's history (firm.view_audit): the whole firm, or some companies / workplaces.
     *
     * @return array{firm: bool, companies: list<int>, workplaces: list<int>}
     */
    public static function reach(User $user, Firm $firm): array
    {
        if ($user->hasPermissionOn(Permission::FirmViewAudit, $firm)) {
            return ['firm' => true, 'companies' => [], 'workplaces' => []];
        }

        $companies = Company::query()->where('firm_id', $firm->id)->with('firm')->get()
            ->filter(fn (Company $company) => $user->hasPermissionOn(Permission::FirmViewAudit, $company))
            ->map(fn (Company $company): int => $company->id);
        $workplaces = Workplace::query()->whereHas('company', fn ($query) => $query->where('firm_id', $firm->id))->with('company.firm')->get()
            ->filter(fn (Workplace $workplace) => $user->hasPermissionOn(Permission::FirmViewAudit, $workplace))
            ->map(fn (Workplace $workplace): int => $workplace->id);

        return ['firm' => false, 'companies' => array_values($companies->all()), 'workplaces' => array_values($workplaces->all())];
    }

    /**
     * İşlem Geçmişi filters (page and Excel export share them).
     *
     * @param  Builder<self>  $query
     * @param  array{q?: string, user?: string|int, company?: string|int, workplace?: string|int, module?: string, from?: string, to?: string, record?: string}  $filters
     */
    public function scopeFilter(Builder $query, array $filters): void
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $user = (string) ($filters['user'] ?? '');
        $company = (string) ($filters['company'] ?? '');
        $workplace = (string) ($filters['workplace'] ?? '');
        $module = (string) ($filters['module'] ?? '');
        $from = (string) ($filters['from'] ?? '');
        $to = (string) ($filters['to'] ?? '');
        $record = (string) ($filters['record'] ?? '');

        $query
            ->when($q !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('description', 'like', '%'.$q.'%')
                ->orWhere('properties', 'like', '%'.$q.'%')
                ->orWhere('ip_address', $q)))
            ->when($user !== '', fn ($query) => $query->where('user_id', (int) $user))
            ->when($company !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('company_id', (int) $company)
                ->orWhereIn('workplace_id', Workplace::withTrashed()->where('company_id', (int) $company)->select('id'))))
            ->when($workplace !== '', fn ($query) => $query->where('workplace_id', (int) $workplace))
            ->when($module !== '', fn ($query) => $query->where('event', 'like', $module.'.%'))
            ->when($from !== '', fn ($query) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($to !== '', fn ($query) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->when(str_contains($record, ':'), function ($query) use ($record) {
                [$type, $id] = explode(':', $record, 2);
                $class = ['personel' => Employee::class, 'isyeri' => Workplace::class, 'sirket' => Company::class][$type] ?? null;

                $query->when($class !== null, fn ($query) => $query->where('subject_type', (new $class)->getMorphClass())->where('subject_id', (int) $id));
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
            'event' => AuditEvent::class,
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
