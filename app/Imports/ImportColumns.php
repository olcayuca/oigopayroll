<?php

namespace App\Imports;

use App\Enums\ImportType;
use App\Support\Fields\CompanyFields;
use App\Support\Fields\Field;
use App\Support\Fields\FirmFields;
use App\Support\Fields\WorkplaceFields;
use App\Support\Text;

final class ImportColumns
{
    /**
     * @return list<Field>
     */
    public static function for(ImportType $type): array
    {
        return match ($type) {
            ImportType::Firm => FirmFields::all(),
            ImportType::Company => CompanyFields::all(),
            ImportType::Workplace => WorkplaceFields::all(),
        };
    }

    /**
     * Map header keys (label, aliases and the raw field key) to fields.
     *
     * @return array<string, Field>
     */
    public static function headerMap(ImportType $type): array
    {
        $map = [];

        foreach (self::for($type) as $field) {
            foreach ([$field->key, $field->label, ...$field->aliases] as $header) {
                $map[Text::key($header)] ??= $field;
            }
        }

        return $map;
    }
}
