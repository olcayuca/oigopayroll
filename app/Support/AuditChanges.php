<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Definition;
use App\Models\District;
use App\Models\LaborSector;
use App\Models\Province;
use App\Models\RiskClass;
use App\Models\Sector;
use App\Models\User;
use App\Models\Workplace;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Field-level "old → new" list of an update, for the İşlem Geçmişi detail.
 *
 * Values are stored as display text (labels, names, Turkish formats) so the history stays readable
 * even after the referenced records change. Encrypted columns never reveal their values.
 */
final class AuditChanges
{
    private const IGNORED = ['updated_at', 'created_at', 'deleted_at', 'tckn_hash', 'created_by', 'password', 'remember_token',
        'two_factor_secret', 'two_factor_recovery_codes'];

    private const MASK = '••••••';

    /**
     * Snapshot the raw attributes before saving: pass the result to between().
     *
     * @return array<string, mixed>
     */
    public static function snapshot(Model $model): array
    {
        return $model->getRawOriginal();
    }

    /**
     * @param  array<string, mixed>  $before  raw attributes from snapshot()
     * @param  array<string, string>  $labels  column => Turkish label
     * @return list<array{field: string, label: string, old: string, new: string}>
     */
    public static function between(Model $model, array $before, array $labels = []): array
    {
        $changes = [];
        $casts = $model->getCasts();

        foreach (array_keys($model->getChanges()) as $field) {
            if (in_array($field, self::IGNORED, true)) {
                continue;
            }

            $old = $before[$field] ?? null;
            $new = $model->getAttributes()[$field] ?? null;
            $cast = $casts[$field] ?? null;

            if ($cast === 'encrypted' || (is_string($cast) && str_starts_with($cast, 'encrypted'))) {
                $changes[] = ['field' => $field, 'label' => $labels[$field] ?? self::humanize($field),
                    'old' => blank($old) ? '—' : self::MASK, 'new' => blank($new) ? '—' : self::MASK.' (değiştirildi)'];

                continue;
            }

            $oldText = self::display($field, $old, $cast);
            $newText = self::display($field, $new, $cast);

            if ($oldText !== $newText) {
                $changes[] = ['field' => $field, 'label' => $labels[$field] ?? self::humanize($field), 'old' => $oldText, 'new' => $newText];
            }
        }

        return $changes;
    }

    /**
     * Key values of a new record (non-encrypted, non-empty), same shape as between() with old "—".
     *
     * @param  list<string>  $fields
     * @param  array<string, string>  $labels
     * @return list<array{field: string, label: string, old: string, new: string}>
     */
    public static function created(Model $model, array $fields, array $labels = []): array
    {
        $casts = $model->getCasts();
        $changes = [];

        foreach ($fields as $field) {
            $raw = $model->getAttributes()[$field] ?? null;
            $cast = $casts[$field] ?? null;

            if (blank($raw) || $cast === 'encrypted') {
                continue;
            }

            $changes[] = ['field' => $field, 'label' => $labels[$field] ?? self::humanize($field), 'old' => '—', 'new' => self::display($field, $raw, $cast)];
        }

        return $changes;
    }

    private static function display(string $field, mixed $value, mixed $cast): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        try {
            if (is_string($cast) && enum_exists($cast) && is_subclass_of($cast, BackedEnum::class)) {
                $case = $cast::tryFrom($value);

                return $case !== null && method_exists($case, 'label') ? $case->label() : (string) $value;
            }

            if ($cast === 'boolean' || $cast === 'bool') {
                return (bool) $value ? 'Evet' : 'Hayır';
            }

            if (in_array($cast, ['date', 'immutable_date'], true)) {
                return Carbon::parse((string) $value)->format('d.m.Y');
            }

            if (in_array($cast, ['datetime', 'immutable_datetime'], true) || $value instanceof CarbonInterface) {
                return Carbon::parse((string) $value)->format('d.m.Y H:i');
            }

            if (is_string($cast) && str_starts_with($cast, 'decimal')) {
                $decimals = (int) (explode(':', $cast)[1] ?? 2);

                return number_format((float) $value, $decimals, ',', '.');
            }

            if (is_string($cast) && in_array($cast, ['array', 'json', 'collection'], true)) {
                $decoded = is_string($value) ? json_decode($value, true) : $value;

                return is_array($decoded) ? collect($decoded)->filter(fn ($item) => filled($item))->map(fn ($item, $key) => "{$key}: ".(is_scalar($item) ? $item : json_encode($item)))->implode(', ') ?: '—' : (string) $value;
            }

            $name = self::reference($field, $value);
            if ($name !== null) {
                return $name;
            }

            // TIME columns: "09:00:00" and "09:00" are the same value.
            if (is_string($value) && preg_match('/^\d{2}:\d{2}(:00)?$/', $value)) {
                return substr($value, 0, 5);
            }
        } catch (Throwable) {
            // fall through to the raw value
        }

        $text = is_scalar($value) ? (string) $value : (json_encode($value) ?: '');

        return mb_strlen($text) > 160 ? mb_substr($text, 0, 157).'…' : $text;
    }

    /**
     * Name of a referenced record for *_id columns.
     */
    private static function reference(string $field, mixed $id): ?string
    {
        if (! str_ends_with($field, '_id') || ! is_numeric($id)) {
            return null;
        }

        $id = (int) $id;

        return match ($field) {
            'company_id' => Company::withTrashed()->find($id)?->short_name,
            'workplace_id' => Workplace::withTrashed()->find($id)?->branch_name,
            'sector_id' => Sector::find($id)?->name,
            'province_id' => Province::find($id)?->name,
            'district_id' => District::find($id)?->name,
            'risk_class_id' => RiskClass::find($id)?->name,
            'labor_sector_id' => ($sector = LaborSector::find($id)) ? $sector->id.' · '.$sector->name : null,
            'specialist_id', 'user_id' => User::find($id)?->name,
            'upper_unit_id', 'unit_id', 'job_family_id', 'title_id', 'position_id', 'level_id', 'cost_group_id', 'parent_id' => Definition::find($id)?->name,
            default => null,
        } ?? '#'.$id;
    }

    private static function humanize(string $field): string
    {
        return ucfirst(str_replace(['_id', '_'], ['', ' '], $field));
    }
}
