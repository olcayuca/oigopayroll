<?php

namespace App\Imports;

use App\Enums\ImportType;
use App\Support\Fields\Field;
use App\Support\Text;
use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Reads the first sheet of an uploaded .xlsx / .xls / .csv file into rows keyed by field key.
 */
class SpreadsheetReader
{
    /**
     * @return array{errors: list<string>, columns: list<string>, rows: array<int, array<string, mixed>>}
     */
    public function read(ImportType $type, string $path, string $originalName): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);

            if ($reader instanceof Csv || str_ends_with(mb_strtolower($originalName), '.csv')) {
                $reader = new Csv;
                $reader->setDelimiter(null);
                $reader->setInputEncoding('UTF-8');
            }

            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
        } catch (Throwable) {
            return ['errors' => ['Dosya okunamadı. Lütfen sistemden indirilen Excel şablonunu kullanın.'], 'columns' => [], 'rows' => []];
        }

        $headerMap = ImportColumns::headerMap($type);

        // A workbook may hold several sheets (the customer setup file has "Firma Bilgileri" and
        // "Personel Bilgileri"): read the one whose headers match this import best.
        $sheet = collect($spreadsheet->getAllSheets())->sortByDesc(function ($candidate) use ($headerMap) {
            $headers = $candidate->rangeToArray('A1:'.$candidate->getHighestDataColumn().'1', null, false, false)[0] ?? [];

            return count(array_filter($headers, fn ($header) => is_scalar($header) && isset($headerMap[Text::key(str_replace('*', '', (string) $header))])));
        })->first() ?? $spreadsheet->getSheet(0);
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $highestRow = $sheet->getHighestDataRow();

        $columns = [];
        $foundKeys = [];
        $errors = [];

        for ($col = 1; $col <= $highestColumn; $col++) {
            $header = $sheet->getCell([$col, 1])->getValue();
            $field = $headerMap[Text::key(is_scalar($header) ? (string) $header : '')] ?? null;

            if ($field === null) {
                continue;
            }

            if (isset($foundKeys[$field->key])) {
                $errors[] = "\"{$field->label}\" sütunu dosyada birden fazla kez yer alıyor.";

                continue;
            }

            $columns[$col] = $field;
            $foundKeys[$field->key] = true;
        }

        // Credential columns may be left out: an update keeps the stored values, and new rows
        // are still checked for them one by one.
        foreach (ImportColumns::for($type) as $field) {
            if ($field->required && $field->type !== Field::SECRET && ! isset($foundKeys[$field->key])) {
                $errors[] = "Zorunlu \"{$field->label}\" sütunu dosyada bulunamadı.";
            }
        }

        $rows = [];

        if ($errors === []) {
            for ($row = 2; $row <= $highestRow; $row++) {
                $values = [];

                foreach ($columns as $col => $field) {
                    $values[$field->key] = $this->convert($field, $sheet->getCell([$col, $row])->getValue());
                }

                // The customer's personnel sheet keeps its dropdown lists in the first rows (Doktora, Lisans…):
                // a row without any identity (sicil, TCKN, ad, soyad) is not a person.
                $isListRow = $type === ImportType::Employee
                    && collect(['registry_no', 'tckn', 'first_name', 'last_name'])->every(fn ($key) => ($values[$key] ?? null) === null);

                if (! $isListRow && array_filter($values, fn ($value) => $value !== null && ! self::isNote($value)) !== []) {
                    $rows[$row] = $values;
                }
            }

            if ($rows === []) {
                $errors[] = 'Dosyada aktarılacak satır bulunamadı.';
            }
        }

        $spreadsheet->disconnectWorksheets();

        return ['errors' => $errors, 'columns' => array_keys($foundKeys), 'rows' => $rows];
    }

    /**
     * Example / instruction texts customers leave in their sheets ("ÖRNEĞİN: 20 (GENEL İŞLER)",
     * "Kırmızı alanlar zorunludur.") are not data.
     */
    private static function isNote(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $text = mb_strtolower(strtr(trim($value), ['I' => 'ı', 'İ' => 'i']));

        return preg_match('/^(örneğin\b|örnek\s*:|örn\s*[:.]|kırmızı alanlar|zorunlu alanlar)/u', $text) === 1;
    }

    private function convert(Field $field, mixed $value): mixed
    {
        if ($value === null || (is_string($value) && trim($value) === '') || self::isNote($value)) {
            return null;
        }

        return match ($field->type) {
            Field::DATE => $this->toDate($value),
            Field::BOOLEAN => $this->toBoolean($value),
            default => $this->toText($value),
        };
    }

    private function toText(mixed $value): ?string
    {
        if (is_float($value) && floor($value) === $value && abs($value) < 1e15) {
            return sprintf('%.0f', $value);
        }

        return is_scalar($value) ? trim((string) $value) : null;
    }

    private function toDate(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            return ExcelDate::excelToDateTimeObject($value)->format('Y-m-d');
        }

        $text = $this->toText($value) ?? '';

        foreach (['d.m.Y', 'd/m/Y', 'd-m-Y', 'Y-m-d'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $text);

            if ($date !== false && $date->format($format) === $text) {
                return $date->format('Y-m-d');
            }
        }

        return $text;
    }

    private function toBoolean(mixed $value): bool|string|null
    {
        if (is_bool($value)) {
            return $value;
        }

        $key = Text::key($this->toText($value));

        return match ($key) {
            'evet', 'e', 'var', '1', 'true', 'x' => true,
            'hayir', 'h', 'yok', '0', 'false' => false,
            default => $this->toText($value),
        };
    }
}
