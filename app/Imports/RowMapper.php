<?php

namespace App\Imports;

use App\Enums\CompanyType;
use App\Enums\HazardClass;
use App\Enums\ImportType;
use App\Enums\RegistrationType;
use App\Enums\WorkplaceKind;
use App\Enums\WorkplaceType;
use App\Models\LaborSector;
use App\Models\RiskClass;
use App\Models\Sector;
use App\Support\Text;
use BackedEnum;

/**
 * Turns spreadsheet values (labels, names) into model attributes (enum values, ids).
 *
 * Unknown list values are passed through unchanged so the regular validation rejects them.
 */
class RowMapper
{
    /**
     * @var array<string, array<string, int>>
     */
    private array $lookups = [];

    /**
     * @param  array<string, mixed>  $row
     * @return array{data: array<string, mixed>, errors: array<string, list<string>>}
     */
    public function map(ImportType $type, array $row): array
    {
        return match ($type) {
            ImportType::Firm => ['data' => $row, 'errors' => []],
            ImportType::Company => $this->company($row),
            ImportType::Workplace => $this->workplace($row),
            // Personnel values are normalised with the firm context (EmployeeInput) in ImportService.
            ImportType::Employee, ImportType::Definition => ['data' => $row, 'errors' => []],
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{data: array<string, mixed>, errors: array<string, list<string>>}
     */
    private function company(array $row): array
    {
        $errors = [];

        $row['company_type'] = $this->enum(CompanyType::class, $row['company_type'] ?? null);
        $row['sector_id'] = $this->lookup('sector_id', Sector::class, $row['sector'] ?? null, 'Şirket Sektörü', $errors);
        unset($row['sector']);

        return ['data' => $row, 'errors' => $errors];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{data: array<string, mixed>, errors: array<string, list<string>>}
     */
    private function workplace(array $row): array
    {
        $errors = [];

        $row['workplace_type'] = $this->enum(WorkplaceType::class, $row['workplace_type'] ?? null);
        $row['workplace_kind'] = $this->enum(WorkplaceKind::class, $row['workplace_kind'] ?? null);
        $row['registration_type'] = $this->enum(RegistrationType::class, $row['registration_type'] ?? null);
        $row['hazard_class'] = $this->enum(HazardClass::class, $row['hazard_class'] ?? null);
        $row['risk_class_id'] = $this->lookup('risk_class_id', RiskClass::class, $row['risk_class'] ?? null, 'Risk Sınıfı', $errors);
        $row['labor_sector_id'] = $this->lookup('labor_sector_id', LaborSector::class, $row['labor_sector'] ?? null, 'ÇSGB İşkolu', $errors);
        $row['has_union'] ??= false;
        unset($row['risk_class'], $row['labor_sector']);

        return ['data' => $row, 'errors' => $errors];
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     */
    private function enum(string $enum, mixed $value): mixed
    {
        if (! is_string($value) || ! method_exists($enum, 'fromLabelOrValue')) {
            return $value;
        }

        $case = $enum::fromLabelOrValue($value);

        return $case instanceof BackedEnum ? $case->value : $value;
    }

    /**
     * Resolve a name (or id) from a reference table.
     *
     * @param  class-string<Sector|RiskClass|LaborSector>  $model
     * @param  array<string, list<string>>  $errors
     */
    private function lookup(string $cacheKey, string $model, mixed $value, string $label, array &$errors): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $this->lookups[$cacheKey] ??= $model::query()->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [Text::key((string) $name) => (int) $id])
            ->all();

        $text = is_scalar($value) ? (string) $value : '';

        // İşkolu by number: "20", "20 (GENEL İŞLER)", "20 - Genel İşler"
        if ($model === LaborSector::class && preg_match('/^\s*(\d+)\b/', $text, $number) && in_array((int) $number[1], $this->lookups[$cacheKey], true)) {
            return (int) $number[1];
        }

        $id = $this->lookups[$cacheKey][Text::key($text)] ?? null;

        if ($id === null) {
            $errors[$cacheKey][] = "{$label} listede bulunamadı: \"{$text}\".";
        }

        return $id;
    }
}
