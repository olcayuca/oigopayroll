<?php

namespace App\Imports;

use App\Actions\Companies\SaveCompany;
use App\Actions\Workplaces\SaveWorkplace;
use App\Enums\ImportStatus;
use App\Enums\ImportType;
use App\Models\Company;
use App\Models\DataImport;
use App\Models\DataImportRow;
use App\Models\Firm;
use App\Models\User;
use App\Models\Workplace;
use App\Validation\CompanyRules;
use App\Validation\WorkplaceInput;
use App\Validation\WorkplaceRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Excel bulk import: upload → check & preview → confirm → records created.
 *
 * Nothing is written to companies / workplaces until confirm() is called on an
 * error-free preview; confirm() re-validates and is all-or-nothing.
 * Authorization is the caller's job (CompanyPolicy::import / WorkplacePolicy::import).
 */
class ImportService
{
    public function __construct(
        private SpreadsheetReader $reader,
        private RowMapper $mapper,
        private SaveCompany $saveCompany,
        private SaveWorkplace $saveWorkplace,
    ) {
        //
    }

    /**
     * Read and validate the file, storing a preview.
     */
    public function preview(ImportType $type, Firm $firm, string $path, string $originalName, ?User $user = null): DataImport
    {
        if (! $firm->isActive()) {
            throw ValidationException::withMessages(['firm' => 'Toplu aktarım yalnızca aktif firmalar için yapılabilir.']);
        }

        $parsed = $this->reader->read($type, $path, $originalName);

        return DB::transaction(function () use ($type, $firm, $originalName, $user, $parsed) {
            $import = DataImport::create([
                'type' => $type,
                'status' => ImportStatus::Validated,
                'firm_id' => $firm->id,
                'user_id' => $user?->id,
                'original_filename' => $originalName,
                'file_errors' => $parsed['errors'] ?: null,
                'total_rows' => count($parsed['rows']),
            ]);

            $seen = [];
            $errorRows = 0;

            foreach ($parsed['rows'] as $rowNumber => $raw) {
                ['data' => $data, 'errors' => $errors] = $this->mapper->map($type, $raw);

                $errors = $this->mergeErrors($this->validate($type, $firm, $data), $errors);
                $errors = $this->mergeErrors($errors, $this->duplicatesInFile($type, $data, $rowNumber, $seen));

                $import->rows()->create([
                    'row_number' => $rowNumber,
                    'data' => $data,
                    'errors' => $errors ?: null,
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

        try {
            DB::transaction(function () use ($import, $user, &$failures) {
                $firm = $import->firm()->lockForUpdate()->firstOrFail();

                foreach ($import->rows as $row) {
                    try {
                        $record = $this->create($import->type, $firm, $row->data, $user);

                        $row->createdRecord()->associate($record)->save();
                    } catch (ValidationException $e) {
                        /** @var array<string, list<string>> $messages */
                        $messages = $e->errors();
                        $failures[$row->id] = $messages;
                    }
                }

                if ($failures !== []) {
                    throw new RuntimeException('import-failed');
                }

                $import->update(['status' => ImportStatus::Completed, 'completed_at' => now()]);
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
                'import' => 'Veriler önizlemeden sonra değişti; '.count($failures).' satır artık geçersiz. Hiçbir kayıt oluşturulmadı.',
            ]);
        }

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
     * @param  array<string, mixed>  $data
     */
    private function create(ImportType $type, Firm $firm, array $data, ?User $user): Company|Workplace
    {
        if ($type === ImportType::Company) {
            return $this->saveCompany->create($firm, $data, $user);
        }

        $company = $this->findCompany($firm, $data['company_no'] ?? null);

        if ($company === null) {
            throw ValidationException::withMessages(['company_no' => 'Şirket numarası bu firmada bulunamadı.']);
        }

        unset($data['company_no']);

        return $this->saveWorkplace->create($company, $data, $user);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, list<string>>
     */
    private function validate(ImportType $type, Firm $firm, array $data): array
    {
        if ($type === ImportType::Company) {
            $validator = Validator::make($data, CompanyRules::rules($data), [], CompanyRules::attributes());

            /** @var array<string, list<string>> */
            return $validator->errors()->toArray();
        }

        $company = $this->findCompany($firm, $data['company_no'] ?? null);

        if ($company === null) {
            return ['company_no' => ['Şirket Numarası bu firmada bulunamadı; önce şirketi oluşturun.']];
        }

        $data = WorkplaceInput::normalize($data);
        $validator = Validator::make($data, WorkplaceRules::rules($data, $company), [], WorkplaceRules::attributes());

        /** @var array<string, list<string>> */
        return $validator->errors()->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, int>  $seen
     * @return array<string, list<string>>
     */
    private function duplicatesInFile(ImportType $type, array $data, int $rowNumber, array &$seen): array
    {
        $keys = $type === ImportType::Company
            ? ['company_no' => $data['company_no'] ?? null]
            : [
                'workplace_no' => isset($data['workplace_no']) ? ($data['company_no'] ?? '').'|'.$data['workplace_no'] : null,
                'sgk_registry_no' => $data['sgk_registry_no'] ?? null,
            ];

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

    private function findCompany(Firm $firm, mixed $companyNo): ?Company
    {
        if (! is_string($companyNo) || $companyNo === '') {
            return null;
        }

        return $firm->companies()->where('company_no', $companyNo)->first();
    }
}
