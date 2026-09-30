<?php

namespace App\Support\Fields;

use App\Enums\CompanyType;
use App\Models\Sector;

/**
 * Şirket columns, in template order.
 */
final class CompanyFields
{
    /**
     * @return list<Field>
     */
    public static function all(): array
    {
        return [
            new Field('company_no', 'Şirket Numarası', required: true, hint: 'Sistem genelinde benzersiz olmalıdır.'),
            new Field('title', 'Şirket Adı / Unvanı', required: true, aliases: ['Şirket Adı', 'Şirket Unvanı']),
            new Field('short_name', 'Şirket Kısa Adı', required: true),
            new Field('company_type', 'Şirket Tipi', required: true, type: Field::SELECT,
                options: fn () => array_values(CompanyType::options())),
            new Field('sector', 'Şirket Sektörü', required: true, type: Field::SELECT,
                options: fn () => Field::strings(Sector::query()->active()->orderBy('name')->pluck('name'))),
            new Field('tax_number', 'Vergi Numarası', required: true,
                hint: '10 haneli VKN. Şahıs firmalarında 11 haneli T.C. kimlik numarası.'),
            new Field('tax_office', 'Vergi Dairesi', required: true),
            new Field('website', 'Web Adresi', hint: 'https:// ile başlamalıdır.'),
            new Field('kep_address', 'KEP Adresi'),
            new Field('trade_registry_no', 'Ticaret Sicil Numarası'),
            new Field('mersis_no', 'MERSİS Numarası', hint: '16 hane.'),
            new Field('phone', 'Telefon'),
            new Field('address', 'Adres'),
        ];
    }
}
