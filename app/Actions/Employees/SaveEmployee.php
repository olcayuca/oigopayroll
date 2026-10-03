<?php

namespace App\Actions\Employees;

use App\Enums\AuditEvent;
use App\Enums\DefinitionType;
use App\Models\Definition;
use App\Models\Employee;
use App\Models\Firm;
use App\Models\User;
use App\Support\Audit;
use App\Support\AuditChanges;
use App\Support\Text;
use App\Validation\EmployeeInput;
use App\Validation\EmployeeRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Create or update a personnel record. Used by the form and the Excel import.
 * Authorization is the caller's job (EmployeePolicy); this enforces the business rules.
 */
class SaveEmployee
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function create(Firm $firm, array $input, ?User $user = null): Employee
    {
        if (! $firm->isActive()) {
            throw ValidationException::withMessages(['firm' => 'Personel yalnızca aktif firmalara eklenebilir.']);
        }

        return DB::transaction(function () use ($firm, $input, $user) {
            $data = $this->validate($firm, $input);
            $employee = Employee::create([...$data, 'firm_id' => $firm->id, 'status' => $data['status'] ?? Employee::ACTIVE, 'created_by' => $user?->id]);

            Audit::log(AuditEvent::EmployeeCreated, "Personel oluşturuldu: {$employee->registry_no} {$employee->fullName()}", $employee, [
                'changes' => AuditChanges::created($employee, ['registry_no', 'first_name', 'last_name', 'company_id', 'workplace_id', 'title_id',
                    'position_id', 'hire_date', 'wage', 'wage_type', 'currency'], EmployeeRules::attributes()),
            ]);

            return $employee;
        });
    }

    /**
     * Blank encrypted fields (TCKN, IBAN, hesap no) keep their stored values.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(Employee $employee, array $input): Employee
    {
        foreach (Employee::SECRET_FIELDS as $field) {
            if (array_key_exists($field, $input) && blank($input[$field])) {
                unset($input[$field]);
            }
        }

        return DB::transaction(function () use ($employee, $input) {
            $before = AuditChanges::snapshot($employee);
            $employee->update($this->validate($employee->firm, $input, $employee));

            if ($employee->wasChanged()) {
                // TCKN / IBAN / hesap no appear masked ("değiştirildi"): their values never reach the log.
                Audit::log(AuditEvent::EmployeeUpdated, "Personel güncellendi: {$employee->registry_no} {$employee->fullName()}", $employee, [
                    'changes' => AuditChanges::between($employee, $before, EmployeeRules::attributes()),
                ]);
            }

            return $employee;
        });
    }

    public function delete(Employee $employee): void
    {
        $employee->delete();
        Audit::log(AuditEvent::EmployeeDeleted, "Personel silindi: {$employee->registry_no} {$employee->fullName()}", $employee);
    }

    /**
     * Normalise, validate, then create definitions introduced by name ("yeni:…").
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validate(Firm $firm, array $input, ?Employee $ignore = null): array
    {
        $input = EmployeeInput::normalize($input, $firm);

        $validator = Validator::make($input, EmployeeRules::rules($input, $firm, $ignore), [], EmployeeRules::attributes());

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();

            // The TCKN uniqueness is checked on its hash; report it on the TCKN field.
            if (isset($errors['tckn_hash'])) {
                $errors['tckn'] = ['Bu TC Kimlik No ile kayıtlı bir personel zaten var.'];
                unset($errors['tckn_hash']);
            }

            throw ValidationException::withMessages($errors);
        }

        $data = $validator->validated();

        if (isset($data['tckn'])) {
            $data['tckn_hash'] = Employee::hashTckn((string) $data['tckn']);
        }

        // The TIME column reads back "09:00:00": store the same form so an unchanged time is not "changed".
        foreach (['shift_start', 'shift_end'] as $field) {
            if (is_string($data[$field] ?? null) && strlen($data[$field]) === 5) {
                $data[$field] .= ':00';
            }
        }

        return $this->createNewDefinitions($firm, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function createNewDefinitions(Firm $firm, array $data): array
    {
        foreach (DefinitionType::cases() as $type) {
            if (is_numeric($data[$type->employeeColumn()] ?? null)) {
                $data[$type->employeeColumn()] = (int) $data[$type->employeeColumn()];
            }
        }

        // Parents first, so a new unit hangs under its upper unit and a new position under its unit.
        foreach ([DefinitionType::UpperUnit, DefinitionType::Unit, DefinitionType::Position, DefinitionType::Title,
            DefinitionType::JobFamily, DefinitionType::Level, DefinitionType::CostGroup] as $type) {
            $column = $type->employeeColumn();
            $value = $data[$column] ?? null;

            if (! is_string($value) || ! str_starts_with($value, EmployeeInput::NEW_PREFIX)) {
                continue;
            }

            $name = substr($value, strlen(EmployeeInput::NEW_PREFIX));
            $parentColumn = $type->parentType()?->employeeColumn();
            $parentId = $parentColumn !== null && is_int($data[$parentColumn] ?? null) ? $data[$parentColumn] : null;

            $definition = EmployeeInput::findDefinition($firm, $type, $name) ?? Definition::create([
                'firm_id' => $firm->id,
                'type' => $type,
                'code' => $this->uniqueCode($firm, $type, $name),
                'name' => $name,
                'parent_id' => $parentId,
            ]);

            $data[$column] = $definition->id;
        }

        return $data;
    }

    private function uniqueCode(Firm $firm, DefinitionType $type, string $name): string
    {
        $base = mb_strtoupper(mb_substr(Text::key($name), 0, 8)) ?: 'TANIM';
        $code = $base;

        for ($i = 2; Definition::query()->ofType($firm, $type)->where('code', $code)->exists(); $i++) {
            $code = $base.$i;
        }

        return $code;
    }
}
