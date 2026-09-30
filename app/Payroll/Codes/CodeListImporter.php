<?php

namespace App\Payroll\Codes;

use App\Enums\AuditEvent;
use App\Enums\CodeList;
use App\Models\PayrollCode;
use App\Models\User;
use App\Support\Audit;
use App\Support\Text;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Bulk load a code list (e.g. SGK meslek kodları) from .xlsx / .xls / .csv.
 *
 * Columns are found by header ("Kod", "Meslek Kodu" / "Ad", "Meslek Adı" / "Açıklama");
 * without recognisable headers column A is the code and column B the name.
 * Existing codes are updated, new ones created; invalid rows are reported and skipped.
 */
class CodeListImporter
{
    /**
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public function import(CodeList $list, string $path, string $originalName, ?User $user = null): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);

            if ($reader instanceof Csv || str_ends_with(mb_strtolower($originalName), '.csv')) {
                $reader = new Csv;
                $reader->setDelimiter(null);
                $reader->setInputEncoding('UTF-8');
            }

            $reader->setReadDataOnly(true);
            $sheet = $reader->load($path)->getSheet(0);
        } catch (Throwable) {
            return ['created' => 0, 'updated' => 0, 'errors' => ['Dosya okunamadı.']];
        }

        [$codeCol, $nameCol, $descriptionCol, $firstRow] = $this->columns($sheet);
        [, $pattern] = $list->format();

        $created = $updated = 0;
        $errors = [];
        $lastRow = $sheet->getHighestDataRow();

        DB::transaction(function () use ($sheet, $list, $pattern, $codeCol, $nameCol, $descriptionCol, $firstRow, $lastRow, &$created, &$updated, &$errors) {
            for ($row = $firstRow; $row <= $lastRow; $row++) {
                $code = $this->restoreCode($list, $this->text($sheet->getCell([$codeCol, $row])->getValue()));
                $name = $this->text($sheet->getCell([$nameCol, $row])->getValue());
                $description = $descriptionCol ? $this->text($sheet->getCell([$descriptionCol, $row])->getValue()) : null;

                if ($code === null && $name === null) {
                    continue;
                }

                if ($code === null || preg_match($pattern, $code) !== 1) {
                    $errors[] = "{$row}. satır: geçersiz kod \"{$code}\".";

                    continue;
                }

                if ($name === null) {
                    $errors[] = "{$row}. satır: ad boş.";

                    continue;
                }

                $entry = PayrollCode::firstOrNew(['list' => $list, 'code' => $code]);
                $isNew = ! $entry->exists;
                $entry->fill(['name' => mb_substr($name, 0, 500), 'description' => $description ?? $entry->description]);

                if ($isNew) {
                    $entry->is_active = true;
                }

                if ($isNew || $entry->isDirty()) {
                    $entry->save();
                    $isNew ? $created++ : $updated++;
                }
            }
        });

        Audit::log(AuditEvent::SystemSettingsChanged, "{$list->label()} Excel ile yüklendi: {$created} yeni, {$updated} güncellendi ({$originalName})", null, [
            'list' => $list->value, 'created' => $created, 'updated' => $updated, 'errors' => count($errors),
        ], $user);

        return ['created' => $created, 'updated' => $updated, 'errors' => $errors];
    }

    /**
     * @return array{0: int, 1: int, 2: int|null, 3: int} code column, name column, description column, first data row
     */
    private function columns(Worksheet $sheet): array
    {
        $code = $name = $description = null;
        $lastColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        for ($col = 1; $col <= $lastColumn; $col++) {
            $header = Text::key((string) $sheet->getCell([$col, 1])->getValue());

            if ($code === null && str_contains($header, 'kod')) {
                $code = $col;
            } elseif ($description === null && str_contains($header, 'aciklama')) {
                $description = $col;
            } elseif ($name === null && (str_contains($header, 'ad') || str_contains($header, 'isim') || str_contains($header, 'tanim'))) {
                $name = $col;
            }
        }

        // No recognisable header row: A = code, B = name, data starts on row 1.
        if ($code === null || $name === null) {
            return [1, 2, null, 1];
        }

        return [$code, $name, $description, 2];
    }

    /**
     * Excel turns "01" into 1 and "2411.10" into 2411.1; put the leading / trailing zeros back.
     */
    private function restoreCode(CodeList $list, ?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $width = match ($list) {
            CodeList::DocumentTypes, CodeList::MissingDayReasons => 2,
            CodeList::IncentiveLaws => 5,
            default => null,
        };

        if ($width !== null && ctype_digit($code) && strlen($code) < $width) {
            return str_pad($code, $width, '0', STR_PAD_LEFT);
        }

        if ($list === CodeList::Occupations && preg_match('/^\d{4}\.\d$/', $code) === 1) {
            return $code.'0';
        }

        return $code;
    }

    private function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value) && floor($value) === $value) {
            $value = sprintf('%.0f', $value);
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
