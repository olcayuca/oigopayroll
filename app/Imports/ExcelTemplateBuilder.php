<?php

namespace App\Imports;

use App\Enums\ImportType;
use App\Support\Fields\Field;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds the downloadable .xlsx template: a data sheet with dropdowns, a hidden
 * sheet holding the dropdown values, and an explanation sheet.
 */
class ExcelTemplateBuilder
{
    public const DATA_ROWS = 1000;

    /**
     * Write the template for the given import type and return the file path.
     */
    public function build(ImportType $type, ?string $path = null): string
    {
        $fields = ImportColumns::for($type);
        $spreadsheet = new Spreadsheet;

        $data = $spreadsheet->getActiveSheet();
        $data->setTitle($type === ImportType::Company ? 'Şirketler' : 'İşyerleri');

        $lists = $spreadsheet->createSheet();
        $lists->setTitle('Listeler');

        $help = $spreadsheet->createSheet();
        $help->setTitle('Açıklamalar');

        $listColumn = 0;
        $lastRow = self::DATA_ROWS + 1;

        foreach ($fields as $index => $field) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $range = "{$column}2:{$column}{$lastRow}";

            $data->setCellValue("{$column}1", $field->header());
            $data->getColumnDimension($column)->setWidth(max(14, mb_strlen($field->header()) + 4));
            $data->getStyle("{$column}1")->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB($field->required ? 'FFFDE68A' : 'FFE5E7EB');

            $data->getStyle($range)->getNumberFormat()->setFormatCode(
                $field->type === Field::DATE ? 'dd.mm.yyyy' : NumberFormat::FORMAT_TEXT,
            );

            $options = $field->options();

            if ($options !== []) {
                $listColumn++;
                $listLetter = Coordinate::stringFromColumnIndex($listColumn);
                $lists->setCellValue("{$listLetter}1", $field->label);

                foreach ($options as $row => $option) {
                    $lists->setCellValueExplicit("{$listLetter}".($row + 2), $option, DataType::TYPE_STRING);
                }

                $this->addDropdown($data, $range, "'Listeler'!\${$listLetter}\$2:\${$listLetter}\$".(count($options) + 1), $field);
            }

            $help->setCellValue('A'.($index + 2), $field->header());
            $help->setCellValue('B'.($index + 2), $field->required ? 'Zorunlu' : 'İsteğe bağlı');
            $help->setCellValue('C'.($index + 2), $this->describe($field));
        }

        $data->getStyle('A1:'.Coordinate::stringFromColumnIndex(count($fields)).'1')->getFont()->setBold(true);
        $data->freezePane('A2');

        $help->fromArray(['Sütun', 'Durum', 'Açıklama'], null, 'A1');
        $help->getStyle('A1:C1')->getFont()->setBold(true);
        $help->getColumnDimension('A')->setWidth(42);
        $help->getColumnDimension('B')->setWidth(14);
        $help->getColumnDimension('C')->setWidth(90);

        $lists->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
        $spreadsheet->setActiveSheetIndex(0);

        $path ??= tempnam(sys_get_temp_dir(), 'hrd-template-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    /**
     * Get the download file name for a template.
     */
    public static function filename(ImportType $type): string
    {
        return $type === ImportType::Company ? 'HRD_Sirket_Sablonu.xlsx' : 'HRD_Isyeri_Sablonu.xlsx';
    }

    private function addDropdown(Worksheet $sheet, string $range, string $source, Field $field): void
    {
        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setAllowBlank(! $field->required);
        $validation->setShowDropDown(true);
        $validation->setFormula1($source);

        // İl may be typed manually when it is not in the list, so only warn there.
        $strict = $field->key !== 'province_name';
        $validation->setErrorStyle($strict ? DataValidation::STYLE_STOP : DataValidation::STYLE_INFORMATION);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle($field->label);
        $validation->setError('Lütfen listeden bir değer seçin.');

        $sheet->setDataValidation($range, $validation);
    }

    private function describe(Field $field): string
    {
        $parts = array_filter([
            $field->hint,
            $field->type === Field::SELECT || $field->type === Field::BOOLEAN ? 'Açılır listeden seçin.' : null,
            $field->type === Field::SECRET ? 'Sistemde şifreli saklanır.' : null,
        ]);

        return implode(' ', $parts);
    }
}
