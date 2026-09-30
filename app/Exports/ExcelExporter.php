<?php

namespace App\Exports;

use BackedEnum;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Writes records to a single-sheet .xlsx: bold frozen header, auto filter, text cells for
 * identifiers (leading zeros survive) and real Excel dates.
 */
class ExcelExporter
{
    /**
     * @param  list<ExportColumn>  $columns
     * @param  iterable<mixed>  $records
     * @return array{path: string, rows: int}
     */
    public function write(string $sheetTitle, array $columns, iterable $records, ?string $path = null): array
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($sheetTitle, 0, 31));

        foreach ($columns as $index => $column) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue("{$letter}1", $column->label);
            $sheet->getColumnDimension($letter)->setWidth(max(12, min(50, mb_strlen($column->label) + 4)));
        }

        $row = 1;

        foreach ($records as $record) {
            $row++;

            foreach ($columns as $index => $column) {
                $this->setCell($sheet, $index + 1, $row, $column, ($column->value)($record));
            }
        }

        $lastColumn = Coordinate::stringFromColumnIndex(max(1, count($columns)));
        $header = $sheet->getStyle("A1:{$lastColumn}1");
        $header->getFont()->setBold(true);
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE5E7EB');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}".max(1, $row));

        $path ??= tempnam(sys_get_temp_dir(), 'hrd-export-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return ['path' => $path, 'rows' => $row - 1];
    }

    private function setCell(Worksheet $sheet, int $column, int $row, ExportColumn $definition, mixed $value): void
    {
        if ($value instanceof BackedEnum && method_exists($value, 'label')) {
            $value = $value->label();
        }

        if ($value === null || $value === '') {
            return;
        }

        $cell = $sheet->getCell([$column, $row]);

        if (in_array($definition->type, [ExportColumn::DATE, ExportColumn::DATETIME], true) && $value instanceof DateTimeInterface) {
            $cell->setValue(Date::PHPToExcel($value));
            $cell->getStyle()->getNumberFormat()->setFormatCode($definition->type === ExportColumn::DATE ? 'dd.mm.yyyy' : 'dd.mm.yyyy hh:mm');

            return;
        }

        if ($definition->type === ExportColumn::NUMBER && is_numeric($value)) {
            $cell->setValueExplicit($value + 0, DataType::TYPE_NUMERIC);

            return;
        }

        $text = match (true) {
            is_bool($value) => $value ? 'Evet' : 'Hayır',
            is_scalar($value), $value instanceof \Stringable => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
        };

        // Explicit strings: no formula injection, no lost leading zeros.
        $cell->setValueExplicit($text, DataType::TYPE_STRING);
    }
}
