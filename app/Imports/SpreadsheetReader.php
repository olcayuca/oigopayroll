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
     * @return array{errors: list<string>, rows: array<int, array<string, mixed>>}
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
            return ['errors' => ['Dosya okunamadı. Lütfen sistemden indirilen Excel şablonunu kullanın.'], 'rows' => []];
        }

        $sheet = $spreadsheet->getSheet(0);
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $highestRow = $sheet->getHighestDataRow();

        $headerMap = ImportColumns::headerMap($type);
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

        foreach (ImportColumns::for($type) as $field) {
            if ($field->required && ! isset($foundKeys[$field->key])) {
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

                if (array_filter($values, fn ($value) => $value !== null) !== []) {
                    $rows[$row] = $values;
                }
            }

            if ($rows === []) {
                $errors[] = 'Dosyada aktarılacak satır bulunamadı.';
            }
        }

        $spreadsheet->disconnectWorksheets();

        return ['errors' => $errors, 'rows' => $rows];
    }

    private function convert(Field $field, mixed $value): mixed
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
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
