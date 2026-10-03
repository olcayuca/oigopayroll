<?php

namespace App\Actions\Definitions;

use App\Enums\AuditEvent;
use App\Enums\DefinitionType;
use App\Models\Definition;
use App\Models\Employee;
use App\Models\Firm;
use App\Support\Audit;
use App\Support\AuditChanges;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Maintain a firm's definitions (Tanımlar). Authorization: FirmPolicy::manageDefinitions.
 */
class SaveDefinition
{
    /**
     * @param  array<string, mixed>  $input  code, name, parent_id, extra[], is_active
     */
    public function save(Firm $firm, DefinitionType $type, array $input, ?Definition $definition = null): Definition
    {
        $input = array_map(fn ($value) => is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value, $input);

        $rules = [
            'code' => ['required', 'string', 'max:50',
                Rule::unique('definitions', 'code')->where('firm_id', $firm->id)->where('type', $type->value)->ignore($definition)],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'parent_id' => $type->parentType()
                ? ['nullable', Rule::exists('definitions', 'id')->where('firm_id', $firm->id)->where('type', $type->parentType()->value)]
                : ['prohibited'],
        ];

        foreach (array_keys($type->extraFields()) as $key) {
            $rules["extra.{$key}"] = ['nullable', 'string', 'max:50'];
        }

        $data = Validator::make($input, $rules, [], [
            'code' => 'Kod', 'name' => 'Ad', 'parent_id' => $type->parentType()?->singular() ?? 'Üst kayıt',
            ...collect($type->extraFields())->mapWithKeys(fn ($label, $key) => ["extra.{$key}" => $label])->all(),
        ])->validate();

        $values = [
            'code' => mb_strtoupper((string) $data['code']),
            'name' => $data['name'],
            'is_active' => $data['is_active'] ?? true,
            'parent_id' => $data['parent_id'] ?? null,
            'extra' => $type->extraFields() === [] ? null : array_intersect_key($data['extra'] ?? [], $type->extraFields()),
        ];

        $labels = ['code' => 'Kod', 'name' => 'Ad', 'is_active' => 'Aktif', 'parent_id' => $type->parentType()?->singular() ?? 'Üst kayıt', 'extra' => 'Ek bilgiler'];

        if ($definition) {
            $before = AuditChanges::snapshot($definition);
            $definition->update($values);
            $changes = AuditChanges::between($definition, $before, $labels);
            $verb = 'güncellendi';
        } else {
            $definition = Definition::create([...$values, 'firm_id' => $firm->id, 'type' => $type]);
            $changes = AuditChanges::created($definition, ['code', 'name', 'parent_id'], $labels);
            $verb = 'eklendi';
        }

        if ($changes !== []) {
            Audit::log(AuditEvent::DefinitionChanged, "{$type->singular()} {$verb}: {$definition->code} {$definition->name}", $definition, ['changes' => $changes]);
        }

        return $definition;
    }

    /**
     * Delete an unused definition; used ones can only be made passive.
     */
    public function delete(Definition $definition): void
    {
        $usage = $definition->usageCount();
        $children = Definition::query()->where('parent_id', $definition->id)->count();

        if ($usage > 0 || $children > 0) {
            throw ValidationException::withMessages(['definition' => $usage > 0
                ? "{$usage} personelde kullanılıyor; silmek yerine pasife alın."
                : "{$children} alt kayıt bu tanıma bağlı; önce onları taşıyın veya silin."]);
        }

        $definition->delete();
        Audit::log(AuditEvent::DefinitionChanged, "{$definition->type->singular()} silindi: {$definition->code} {$definition->name}", $definition->firm);
    }

    /**
     * Personnel count per definition id of a type (for the list).
     *
     * @return array<int, int>
     */
    public static function usage(Firm $firm, DefinitionType $type): array
    {
        $column = $type->employeeColumn();

        return Employee::query()->where('firm_id', $firm->id)->whereNotNull($column)
            ->select($column.' as definition_id')->selectRaw('count(*) as total')->groupBy($column)
            ->pluck('total', 'definition_id')->map(fn ($total) => (int) $total)->all();
    }
}
