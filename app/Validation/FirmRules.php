<?php

namespace App\Validation;

use App\Models\Firm;
use App\Rules\TaxNumber;
use Illuminate\Validation\Rule;

/**
 * Firma (client account) fields. Only the name is required.
 */
final class FirmRules
{
    public const FIELDS = ['name', 'title', 'tax_number', 'tax_office', 'contact_name', 'phone', 'email', 'address'];

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(?Firm $ignore = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', new TaxNumber(allowTckn: true), Rule::unique('firms', 'tax_number')->ignore($ignore)],
            'tax_office' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'name' => 'Firma Adı',
            'title' => 'Unvan',
            'tax_number' => 'Vergi Numarası',
            'tax_office' => 'Vergi Dairesi',
            'contact_name' => 'Yetkili Kişi',
            'phone' => 'Telefon',
            'email' => 'E-posta',
            'address' => 'Adres',
        ];
    }

    /**
     * Keep only firm fields and turn blank strings into null.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function clean(array $input): array
    {
        $data = array_intersect_key($input, array_flip(self::FIELDS));

        return array_map(fn ($value) => is_string($value) && trim($value) === '' ? null : (is_string($value) ? trim($value) : $value), $data);
    }
}
