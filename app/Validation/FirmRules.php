<?php

namespace App\Validation;

/**
 * Firm fields are not specified yet; only the name is collected for now.
 */
final class FirmRules
{
    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'name' => 'Firma Adı',
        ];
    }
}
