<?php

namespace App\Imports;

use App\Actions\Companies\SaveCompany;
use App\Actions\Employees\SaveEmployee;
use App\Actions\Firms\CreateFirm;
use App\Actions\Workplaces\SaveWorkplace;
use App\Enums\AuditEvent;
use App\Enums\ImportStatus;
use App\Enums\ImportType;
use App\Models\Company;
use App\Models\DataImport;
use App\Models\DataImportRow;
use App\Models\Employee;
use App\Models\Firm;
use App\Models\User;
use App\Models\Workplace;
use App\Support\Audit;
use App\Support\Text;
use App\Validation\CompanyRules;
use App\Validation\EmployeeInput;
use App\Validation\EmployeeRules;
use App\Validation\FirmRules;
use App\Validation\WorkplaceInput;
use App\Validation\WorkplaceRules;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Excel bulk import: upload → check & preview → confirm → records created or updated.
 *
 * Company and workplace rows are matched to existing records (şirket no; şirket no + işyeri no):
 * a match is updated, anything else is created. An update only touches the columns present in
 * the file, and blank or missing credential columns keep the stored values, so a file exported
 * from the system (which never contains credentials) can be edited and uploaded again.
 *
 * Nothing is written until confirm() is called on an error-free preview; confirm() re-plans
 * every row against the current data and is all-or-nothing.
 * Authorization is the caller's job (CompanyPolicy::import / WorkplacePolicy::import).
 */
class ImportService
{
    public function __construct(
        private SpreadsheetReader $reader,
        private RowMapper $mapper,
        private CreateFirm $createFirm,
        private SaveCompany $saveCompany,
        private SaveWorkplace $saveWorkplace,
        private SaveEmployee $saveEmployee,
    ) {
        //
    }

    /**
     * Read and validate the file, storing a preview.
     */
    public function preview(ImportType $type, ?Firm $firm, string $path, string $originalName, ?User $user = null): DataImport
    {
        // Firm imports (admin) have no firm context; company/workplace imports need an active firm.
        if ($type !== ImportType::Firm && ! $firm?->isActive()) {
            throw ValidationException::withMessages(['firm' => 'Toplu aktarım yalnızca aktif firmalar için yapılabilir.']);
        }

        $parsed = $this->reader->read($type, $path, $originalName);

        return DB::transaction(function () use ($type, $firm, $originalName, $user, $parsed) {
            $import = DataImport::create([
                'type' => $type,
                'status' => ImportStatus::Validated,
                'firm_id' => $firm?->id,
                'user_id' => $user?->id,
                'original_filename' => $originalName,
                'file_errors' => $parsed['errors'] ?: null,
                'columns' => $parsed['columns'],
                'total_rows' => count($parsed['rows']),
            ]);

            $seen = [];
            $errorRows = 0;

            foreach ($parsed['rows'] as $rowNumber => $raw) {
                ['data' => $data, 'errors' => $errors] = $this->mapper->map($type, $raw);

                $plan = $this->plan($type, $firm, $data, $parsed['columns'], $user);
                $errors = $this->mergeErrors($plan['errors'], $errors);
                $errors = $this->mergeErrors($errors, $this->duplicatesInFile($type, $data, $rowNumber, $seen));

                $import->rows()->create([
                    'row_number' => $rowNumber,
                    'data' => $data,
                    'errors' => $errors ?: null,
                    'action' => $errors === [] ? $plan['action'] : null,
                ]);

                $errorRows += $errors === [] ? 0 : 1;
            }

            $import->update(['error_rows' => $errorRows]);

            return $import;
        });
    }

    /**
     * Create all records of a clean preview in one transaction.
     */
    public function confirm(DataImport $import, ?User $user = null): DataImport
    {
        if (! $import->canBeConfirmed()) {
            throw ValidationException::withMessages([
                'import' => 'Bu aktarım onaylanamaz. Hatalı satırları düzeltip dosyayı yeniden yükleyin.',
            ]);
        }

        $failures = [];
        $counts = [DataImportRow::CREATE => 0, DataImportRow::UPDATE => 0];

        try {
            DB::transaction(function () use ($import, $user, &$failures, &$counts) {
                $firm = $import->type === ImportType::Firm ? null : $import->firm()->lockForUpdate()->firstOrFail();

                foreach ($import->rows as $row) {
                    try {
                        $record = $this->apply($import, $firm, $row, $user);

                        if ($record !== null) {
                            $row->createdRecord()->associate($record)->save();
                            $counts[$row->action === DataImportRow::UPDATE ? DataImportRow::UPDATE : DataImportRow::CREATE]++;
                        }
                    } catch (ValidationException $e) {
                        /** @var array<string, list<string>> $messages */
                        $messages = $e->errors();
                        $failures[$row->id] = $messages;
                    }
                }

                if ($failures !== []) {
                    throw new RuntimeException('import-failed');
                }

                $import->update([
                    'status' => ImportStatus::Completed,
                    'completed_at' => now(),
                    'created_rows' => $counts[DataImportRow::CREATE],
                    'updated_rows' => $counts[DataImportRow::UPDATE],
                ]);
            });
        } catch (RuntimeException $e) {
            if ($e->getMessage() !== 'import-failed') {
                throw $e;
            }

            foreach ($failures as $rowId => $errors) {
                DataImportRow::whereKey($rowId)->update(['errors' => json_encode($errors)]);
            }

            $import->update(['error_rows' => count($failures)]);

            throw ValidationException::withMessages([
                'import' => 'Veriler önizlemeden sonra değişti; '.count($failures).' satır artık geçersiz. Hiçbir değişiklik yapılmadı.',
            ]);
        }

        Audit::log(AuditEvent::ImportCompleted, "Excel aktarımı: {$import->type->label()} {$counts[DataImportRow::CREATE]} yeni, {$counts[DataImportRow::UPDATE]} güncellendi ({$import->original_filename})", $import->firm, [
            'import_id' => $import->id, 'type' => $import->type->value, 'rows' => $import->total_rows,
            'created' => $counts[DataImportRow::CREATE], 'updated' => $counts[DataImportRow::UPDATE],
        ], $user);

        return $import->refresh();
    }

    /**
     * Discard a preview that has not been applied.
     */
    public function cancel(DataImport $import): void
    {
        if ($import->status === ImportStatus::Validated) {
            $import->rows()->delete();
            $import->update(['status' => ImportStatus::Cancelled]);
        }
    }

    /**
     * Write one row: create, update, or nothing when the record is unchanged.
     */
    private function apply(DataImport $import, ?Firm $firm, DataImportRow $row, ?User $user): Firm|Company|Workplace|Employee|null
    {
        if ($import->type === ImportType::Firm) {
            if ($user === null) {
                throw ValidationException::withMessages(['import' => 'Firma aktarımı için kullanıcı gerekir.']);
            }

            return $this->createFirm->handle($user, $row->data);
        }

        if ($firm === null) {
            throw ValidationException::withMessages(['firm' => 'Firma bulunamadı.']);
        }

        // Re-plan against the current data: records may have changed since the preview.
        $plan = $this->plan($import->type, $firm, $row->data, $import->columns ?? array_keys($row->data), $user);

        if ($plan['errors'] !== []) {
            throw ValidationException::withMessages($plan['errors']);
        }

        $row->action = $plan['action'];
        $target = $plan['target'];

        return match (true) {
            $plan['action'] === DataImportRow::UNCHANGED => null,
            $target instanceof Company => $this->saveCompany->update($target, $plan['input']),
            $target instanceof Workplace => $this->saveWorkplace->update($target, $plan['input']),
            $target instanceof Employee => $this->saveEmployee->update($target, $plan['input']),
            $import->type === ImportType::Employee => $this->saveEmployee->create($firm, $plan['input'], $user),
            $import->type === ImportType::Company => $this->saveCompany->create($firm, $plan['input'], $user),
            $plan['company'] !== null => $this->saveWorkplace->create($plan['company'], $plan['input'], $user),
            default => throw ValidationException::withMessages(['company_no' => 'Şirket numarası bu firmada bulunamadı.']),
        };
    }

    /**
     * Decide what a row does and validate it.
     *
     * @param  array<string, mixed>  $data  mapped row
     * @param  list<string>  $columns  field keys present in the file
     * @return array{action: string, errors: array<string, list<string>>, input: array<string, mixed>, target: Company|Workplace|Employee|null, company: Company|null}
     */
    private function plan(ImportType $type, ?Firm $firm, array $data, array $columns, ?User $user): array
    {
        $result = ['action' => DataImportRow::CREATE, 'errors' => [], 'input' => $data, 'target' => null, 'company' => null];

        if ($type === ImportType::Firm) {
            return [...$result, 'errors' => $this->errors(FirmRules::clean($data), FirmRules::rules(), FirmRules::attributes())];
        }

        if ($firm === null) {
            return [...$result, 'errors' => ['firm' => ['Firma bulunamadı.']]];
        }

        return match ($type) {
            ImportType::Company => $this->planCompany($firm, self::clean($data), $columns, $user, $result),
            ImportType::Employee => $this->planEmployee($firm, self::clean($data), $columns, $user, $result),
            default => $this->planWorkplace($firm, $data, $columns, $user, $result),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $columns
     * @param  array{action: string, errors: array<string, list<string>>, input: array<string, mixed>, target: Company|Workplace|Employee|null, company: Company|null}  $result
     * @return array{action: string, errors: array<string, list<string>>, input: array<string, mixed>, target: Company|Workplace|Employee|null, company: Company|null}
     */
    private function planCompany(Firm $firm, array $data, array $columns, ?User $user, array $result): array
    {
        $companyNo = $data['company_no'] ?? null;
        $existing = is_string($companyNo) ? Company::withTrashed()->where('company_no', $companyNo)->first() : null;

        if ($existing === null) {
            return [...$result, 'input' => $data, 'errors' => $this->errors($data, CompanyRules::rules($data), CompanyRules::attributes())];
        }

        $error = $this->cannotUpdate($existing, $existing->firm_id !== $firm->id, $user, 'şirket');

        if ($error !== null) {
            return [...$result, 'errors' => ['company_no' => [$error]]];
        }

        $input = $this->merge($this->current($existing, array_keys(CompanyRules::rules([]))), $data, $columns, ['sector' => ['sector_id']]);
        $errors = $this->errors($input, CompanyRules::rules($input, $existing), CompanyRules::attributes());

        return [...$result, 'errors' => $errors, 'input' => $input, 'target' => $existing, 'action' => $this->updateAction($existing, $input)];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $columns
     * @param  array{action: string, errors: array<string, list<string>>, input: array<string, mixed>, target: Company|Workplace|Employee|null, company: Company|null}  $result
     * @return array{action: string, errors: array<string, list<string>>, input: array<string, mixed>, target: Company|Workplace|Employee|null, company: Company|null}
     */
    private function planWorkplace(Firm $firm, array $data, array $columns, ?User $user, array $result): array
    {
        $company = $this->findCompany($firm, $data['company_no'] ?? null, $data['company_name'] ?? null);

        if ($company === null) {
            return [...$result, 'errors' => ['company_no' => ['Şirket (numara veya ad) bu firmada bulunamadı; önce şirketi oluşturun.']]];
        }

        unset($data['company_no'], $data['company_name']);
        $result['company'] = $company;
        $workplaceNo = $data['workplace_no'] ?? null;
        $existing = is_string($workplaceNo) && $workplaceNo !== ''
            ? Workplace::withTrashed()->where('company_id', $company->id)->where('workplace_no', $workplaceNo)->first()
            : null;

        if ($existing === null) {
            $input = WorkplaceInput::normalize($data, $company);

            return [...$result, 'input' => $data, 'errors' => $this->errors($input, WorkplaceRules::rules($input, $company), WorkplaceRules::attributes())];
        }

        $error = $this->cannotUpdate($existing, false, $user, 'işyeri');

        if ($error !== null) {
            return [...$result, 'errors' => ['workplace_no' => [$error]]];
        }

        $keys = array_values(array_diff(array_keys(WorkplaceRules::rules([], $company)), Workplace::SECRET_FIELDS));
        $input = $this->merge($this->current($existing, $keys), $data, $columns, [
            'company_no' => [],
            'company_name' => [],
            'risk_class' => ['risk_class_id'],
            'labor_sector' => ['labor_sector_id'],
            'province_name' => ['province_id', 'province_name'],
            'district_name' => ['district_id', 'district_name'],
        ]);
        $normalized = WorkplaceInput::normalize($input, $company);
        $errors = $this->errors($normalized, WorkplaceRules::rules($normalized, $company, $existing, updating: true), WorkplaceRules::attributes());

        return [...$result, 'errors' => $errors, 'input' => $input, 'target' => $existing, 'action' => $this->updateAction($existing, $normalized)];
    }

    /**
     * Personnel rows: matched to existing records by sicil no; Firma / SGK Firma / şube given by name.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $columns
     * @param  array{action: string, errors: array<string, list<string>>, input: array<string, mixed>, target: Company|Workplace|Employee|null, company: Company|null}  $result
     * @return array{action: string, errors: array<string, list<string>>, input: array<string, mixed>, target: Company|Workplace|Employee|null, company: Company|null}
     */
    private function planEmployee(Firm $firm, array $data, array $columns, ?User $user, array $result): array
    {
        $registryNo = $data['registry_no'] ?? null;
        $existing = is_scalar($registryNo) && $registryNo !== ''
            ? Employee::withTrashed()->where('firm_id', $firm->id)->where('registry_no', (string) $registryNo)->first()
            : null;

        // Firma / SGK Firma / şube: resolved from the names in the file, or kept from the record.
        $placement = [];
        $errors = [];
        $workplace = null;

        if ($existing === null || array_intersect(['company_name', 'sgk_company_name', 'workplace_name'], $columns) !== []) {
            $company = $this->resolveCompany($firm, $data['company_name'] ?? null);
            $sgkCompany = $this->resolveCompany($firm, $data['sgk_company_name'] ?? null) ?? $company;
            $workplace = $sgkCompany ? $this->resolveWorkplace($sgkCompany, $data['workplace_name'] ?? null) : null;

            if ($company === null) {
                $errors['company_id'][] = 'Firma bu firmanın şirketleri arasında bulunamadı.';
            }
            if ($workplace === null) {
                $errors['workplace_id'][] = 'İş yeri şube adı SGK firmasının işyerleri arasında bulunamadı.';
            }

            $placement = ['company_id' => $company?->id, 'workplace_id' => $workplace?->id];
        }

        unset($data['company_name'], $data['sgk_company_name'], $data['workplace_name']);

        if ($existing === null) {
            $input = [...$data, ...$placement];

            if ($user !== null && $workplace !== null && ! $user->can('createIn', [Employee::class, $workplace])) {
                $errors['workplace_id'][] = 'Bu işyerine personel ekleme yetkiniz yok.';
            }

            $normalized = EmployeeInput::normalize($input, $firm);
            $errors = $this->mergeErrors($this->employeeErrors($normalized, $firm), $errors);

            return [...$result, 'input' => $input, 'errors' => $errors];
        }

        $error = $this->cannotUpdate($existing, false, $user, 'personel');

        if ($error !== null) {
            return [...$result, 'errors' => ['registry_no' => [$error]]];
        }

        $keys = array_values(array_diff((new Employee)->getFillable(), [...Employee::SECRET_FIELDS, 'firm_id', 'created_by', 'tckn_hash']));
        $input = [...$this->merge($this->current($existing, $keys), $data, $columns, [
            'company_name' => [], 'sgk_company_name' => [], 'workplace_name' => [],
        ]), ...$placement];
        $normalized = EmployeeInput::normalize($input, $firm);
        $errors = $this->mergeErrors($this->employeeErrors($normalized, $firm, $existing), $errors);

        return [...$result, 'errors' => $errors, 'input' => $input, 'target' => $existing, 'action' => $this->updateAction($existing, $normalized)];
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, list<string>>
     */
    private function employeeErrors(array $normalized, Firm $firm, ?Employee $existing = null): array
    {
        $errors = $this->errors($normalized, EmployeeRules::rules($normalized, $firm, $existing), EmployeeRules::attributes());

        if (isset($errors['tckn_hash'])) {
            $errors['tckn'] = ['Bu TC Kimlik No ile kayıtlı bir personel zaten var.'];
            unset($errors['tckn_hash']);
        }

        return $errors;
    }

    /**
     * A company of the firm by number, unvan or short name.
     */
    private function resolveCompany(Firm $firm, mixed $value): ?Company
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        return $firm->companies()->where('company_no', trim((string) $value))->first() ?? $this->findCompany($firm, null, (string) $value);
    }

    /**
     * A workplace of the company by şube adı or işyeri numarası (the only one when left blank).
     */
    private function resolveWorkplace(Company $company, mixed $value): ?Workplace
    {
        $workplaces = $company->workplaces()->get();

        if (! is_scalar($value) || trim((string) $value) === '') {
            return $workplaces->count() === 1 ? $workplaces->first() : null;
        }

        $key = Text::key((string) $value);
        $matches = $workplaces->filter(fn (Workplace $workplace) => Text::key($workplace->branch_name) === $key || Text::key($workplace->workplace_no) === $key);

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * Why an existing record cannot be updated from this import, if it cannot.
     */
    private function cannotUpdate(Company|Workplace|Employee $record, bool $otherFirm, ?User $user, string $label): ?string
    {
        return match (true) {
            $otherFirm => "Bu {$label} numarası başka bir firmada kayıtlı.",
            $record->trashed() => "Bu {$label} çöp kutusunda; önce geri alın.",
            $user !== null && ! $user->can('update', $record) => "Mevcut {$label} kaydını güncelleme yetkiniz yok.",
            default => null,
        };
    }

    /**
     * The record's current values as import input (enums as values, dates as Y-m-d).
     *
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function current(Company|Workplace|Employee $record, array $keys): array
    {
        $values = [];

        foreach ($keys as $key) {
            $value = $record->getAttribute($key);

            $values[$key] = match (true) {
                $value instanceof BackedEnum => $value->value,
                $value instanceof DateTimeInterface => $value->format('Y-m-d'),
                default => $value,
            };
        }

        return $values;
    }

    /**
     * Overwrite the current values with the columns present in the file. Blank credentials are skipped.
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $data
     * @param  list<string>  $columns
     * @param  array<string, list<string>>  $dataKeys  field key => the input keys it fills (default: itself)
     * @return array<string, mixed>
     */
    private function merge(array $current, array $data, array $columns, array $dataKeys): array
    {
        foreach ($columns as $column) {
            foreach ($dataKeys[$column] ?? [$column] as $key) {
                $value = $data[$key] ?? null;

                if (in_array($key, [...Workplace::SECRET_FIELDS, ...Employee::SECRET_FIELDS], true) && ($value === null || $value === '')) {
                    continue;
                }

                $current[$key] = $value;
            }
        }

        return $current;
    }

    /**
     * Whether applying the input would change anything.
     *
     * @param  array<string, mixed>  $input
     */
    private function updateAction(Company|Workplace|Employee $record, array $input): string
    {
        $copy = clone $record;
        $copy->fill(array_intersect_key($input, array_flip($record->getFillable())));

        return $copy->isDirty() ? DataImportRow::UPDATE : DataImportRow::UNCHANGED;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $attributes
     * @return array<string, list<string>>
     */
    private function errors(array $input, array $rules, array $attributes): array
    {
        /** @var array<string, list<string>> */
        return Validator::make($input, $rules, [], $attributes)->errors()->toArray();
    }

    /**
     * Trim strings and turn blanks into null, as SaveCompany does.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function clean(array $data): array
    {
        return array_map(fn ($value) => is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, int>  $seen
     * @return array<string, list<string>>
     */
    private function duplicatesInFile(ImportType $type, array $data, int $rowNumber, array &$seen): array
    {
        $keys = match ($type) {
            ImportType::Firm => ['tax_number' => $data['tax_number'] ?? null],
            ImportType::Company => ['company_no' => $data['company_no'] ?? null],
            ImportType::Employee => [
                'registry_no' => $data['registry_no'] ?? null,
                'tckn' => isset($data['tckn']) && is_scalar($data['tckn']) ? preg_replace('/\D/', '', (string) $data['tckn']) : null,
            ],
            ImportType::Workplace => [
                'workplace_no' => isset($data['workplace_no']) ? ($data['company_no'] ?? Text::key($data['company_name'] ?? '')).'|'.$data['workplace_no'] : null,
                'sgk_registry_no' => $data['sgk_registry_no'] ?? null,
            ],
        };

        $errors = [];

        foreach ($keys as $attribute => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $seenKey = $attribute.':'.mb_strtolower((string) $value);

            if (isset($seen[$seenKey])) {
                $errors[$attribute][] = "Aynı değer dosyada {$seen[$seenKey]}. satırda da var.";
            } else {
                $seen[$seenKey] = $rowNumber;
            }
        }

        return $errors;
    }

    /**
     * Lookup errors win over the generic "required / invalid" messages for the same attribute.
     *
     * @param  array<string, list<string>>  $base
     * @param  array<string, list<string>>  $override
     * @return array<string, list<string>>
     */
    private function mergeErrors(array $base, array $override): array
    {
        foreach ($override as $attribute => $messages) {
            $base[$attribute] = $messages;
        }

        return $base;
    }

    /**
     * Find the row's company by number, or — as in the customer's setup file — by name (unvan or short name).
     */
    private function findCompany(Firm $firm, mixed $companyNo, mixed $companyName = null): ?Company
    {
        if (is_string($companyNo) && $companyNo !== '') {
            return $firm->companies()->where('company_no', $companyNo)->first();
        }

        if (! is_string($companyName) || Text::key($companyName) === '') {
            return null;
        }

        $key = Text::key($companyName);
        $matches = $firm->companies()->get()
            ->filter(fn (Company $company) => in_array($key, [Text::key($company->title), Text::key($company->short_name)], true));

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
