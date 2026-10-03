<?php

namespace App\Validation;

use App\Enums\DefinitionType;
use App\Models\Definition;
use App\Models\Employee;
use App\Models\Firm;
use App\Support\EmployeeOptions;
use App\Support\Text;

/**
 * Normalises personnel input (form or Excel) before validation:
 * Turkish number formats ("82.400,00", "%3"), Evet / Hayır, month names, times, list labels,
 * TCKN / IBAN spacing, and definitions given by name ("upper_unit" => "Genel Müdürlük").
 *
 * A definition name that does not exist yet becomes "yeni:<name>"; SaveEmployee creates it
 * once the row is valid, so a setup file can introduce its own units, titles and positions.
 */
final class EmployeeInput
{
    public const NEW_PREFIX = 'yeni:';

    /** Input key of a definition given by name => its type. */
    public const DEFINITION_NAMES = [
        'upper_unit' => DefinitionType::UpperUnit,
        'unit' => DefinitionType::Unit,
        'job_family' => DefinitionType::JobFamily,
        'title' => DefinitionType::Title,
        'position' => DefinitionType::Position,
        'level' => DefinitionType::Level,
        'cost_group' => DefinitionType::CostGroup,
    ];

    public const DECIMALS = ['wage', 'bes_rate', 'cumulative_tax_base', 'previous_sgk_base_1', 'previous_sgk_base_2', 'cost_group_rate', 'remaining_leave_days'];

    public const BOOLEANS = ['is_minimum_wage', 'minimum_wage_exemption', 'disability_tax_relief', 'is_shift_worker'];

    public const CHOICES = [
        'gender' => EmployeeOptions::GENDER,
        'marital_status' => EmployeeOptions::MARITAL_STATUS,
        'education' => EmployeeOptions::EDUCATION,
        'military_status' => EmployeeOptions::MILITARY,
        'collar' => EmployeeOptions::COLLAR,
        'duty_type' => EmployeeOptions::DUTY_TYPE,
        'insurance_branch' => EmployeeOptions::INSURANCE_BRANCH,
        'sgk_status' => EmployeeOptions::SGK_STATUS,
        'employment_type' => EmployeeOptions::EMPLOYMENT_TYPE,
        'duty_code' => EmployeeOptions::DUTY_CODE,
        'wage_period' => EmployeeOptions::WAGE_PERIOD,
        'currency' => EmployeeOptions::CURRENCY,
        'wage_type' => EmployeeOptions::WAGE_TYPE,
        'rnd_rate' => EmployeeOptions::RND_RATE,
        'disability_degree' => EmployeeOptions::DISABILITY_DEGREE,
        'work_model' => EmployeeOptions::WORK_MODEL,
        'contract_type' => EmployeeOptions::CONTRACT_TYPE,
        'weekly_rest' => EmployeeOptions::WEEKLY_REST,
    ];

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input, Firm $firm): array
    {
        $input = array_map(fn ($value) => is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value, $input);

        if (isset($input['tckn']) && is_scalar($input['tckn'])) {
            $input['tckn'] = preg_replace('/\D/', '', (string) $input['tckn']);
        }
        if (! empty($input['tckn'])) {
            $input['tckn_hash'] = Employee::hashTckn((string) $input['tckn']);
        }

        foreach (['iban', 'account_no'] as $field) {
            if (isset($input[$field]) && is_scalar($input[$field])) {
                $input[$field] = mb_strtoupper(preg_replace('/\s+/', '', (string) $input[$field]) ?? '');
            }
        }

        foreach (self::DECIMALS as $field) {
            if (array_key_exists($field, $input)) {
                $input[$field] = self::number($input[$field]);
            }
        }

        // Percent-formatted Excel cells arrive as fractions: 0.03 → 3, 1 → 100 is ambiguous and kept.
        foreach (['bes_rate', 'cost_group_rate'] as $field) {
            if (is_numeric($input[$field] ?? null) && (float) $input[$field] > 0 && (float) $input[$field] < 1) {
                $input[$field] = (string) round((float) $input[$field] * 100, 2);
            }
        }
        if (is_numeric($input['rnd_rate'] ?? null) && (float) $input['rnd_rate'] > 0 && (float) $input['rnd_rate'] <= 1) {
            $input['rnd_rate'] = (string) round((float) $input['rnd_rate'] * 100);
        }

        foreach (self::BOOLEANS as $field) {
            if (array_key_exists($field, $input)) {
                $input[$field] = self::boolean($input[$field]);
            }
        }

        foreach (self::CHOICES as $field => $options) {
            if (array_key_exists($field, $input)) {
                $input[$field] = EmployeeOptions::match($options, $input[$field]);
            }
        }

        if (array_key_exists('tax_exemption_start_month', $input) && $input['tax_exemption_start_month'] !== null) {
            $input['tax_exemption_start_month'] = EmployeeOptions::month($input['tax_exemption_start_month']);
        }

        foreach (['shift_start', 'shift_end'] as $field) {
            if (array_key_exists($field, $input)) {
                $input[$field] = self::time($input[$field]);
            }
        }

        if (isset($input['sgk_document_type']) && is_scalar($input['sgk_document_type']) && ctype_digit((string) $input['sgk_document_type'])) {
            $input['sgk_document_type'] = str_pad((string) (int) $input['sgk_document_type'], 2, '0', STR_PAD_LEFT);
        }

        return self::resolveDefinitions($input, $firm);
    }

    /**
     * "82.400,00" / "82400.5" / "%3" / 3 → "82400.00"-like numeric strings; anything else unchanged.
     */
    public static function number(mixed $value): mixed
    {
        if ($value === null || is_int($value) || is_float($value)) {
            return $value;
        }

        $text = str_replace(['%', ' ', '₺', 'TL'], '', (string) $value);

        if (str_contains($text, ',')) {
            $text = str_replace(['.', ','], ['', '.'], $text);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $text)) {
            $text = str_replace('.', '', $text); // 82.400 → 82400
        }

        return is_numeric($text) ? $text : $value;
    }

    public static function boolean(mixed $value): mixed
    {
        if ($value === null || is_bool($value)) {
            return $value;
        }

        return match (Text::key((string) $value)) {
            'evet', 'e', 'var', '1', 'true' => true,
            'hayir', 'h', 'yok', '0', 'false' => false,
            default => $value,
        };
    }

    /**
     * "9:00", "09:00:00" or an Excel day fraction (0.375) → "09:00".
     */
    public static function time(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value) && (float) $value < 1) {
            $minutes = (int) round((float) $value * 24 * 60);

            return sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60);
        }

        if (is_string($value) && preg_match('/^(\d{1,2})[:.](\d{2})(:\d{2})?$/', $value, $match)) {
            return sprintf('%02d:%s', (int) $match[1], $match[2]);
        }

        return $value;
    }

    /**
     * Turn "<type>" name keys into "<type>_id" (existing id or "yeni:<name>").
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private static function resolveDefinitions(array $input, Firm $firm): array
    {
        foreach (self::DEFINITION_NAMES as $key => $type) {
            if (! array_key_exists($key, $input)) {
                continue;
            }

            $name = $input[$key];
            unset($input[$key]);
            $column = $type->employeeColumn();

            if ($name === null || ! is_scalar($name)) {
                $input[$column] = null;

                continue;
            }

            $definition = self::findDefinition($firm, $type, (string) $name);
            $input[$column] = $definition !== null ? $definition->id : self::NEW_PREFIX.$name;
        }

        return $input;
    }

    public static function findDefinition(Firm $firm, DefinitionType $type, string $nameOrCode): ?Definition
    {
        $key = Text::key($nameOrCode);

        return Definition::query()->ofType($firm, $type)->get()
            ->first(fn (Definition $definition) => Text::key($definition->name) === $key || Text::key($definition->code) === $key);
    }
}
