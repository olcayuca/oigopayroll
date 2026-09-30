<?php

namespace App\Support\Fields;

/**
 * Firma columns for the admin bulk import, in template order.
 */
final class FirmFields
{
    /**
     * @return list<Field>
     */
    public static function all(): array
    {
        return [
            new Field('name', 'Firma Adı', required: true),
            new Field('title', 'Unvan'),
            new Field('tax_number', 'Vergi Numarası', hint: '10 haneli VKN veya 11 haneli TCKN. Firmalar arasında benzersiz olmalıdır.'),
            new Field('tax_office', 'Vergi Dairesi'),
            new Field('contact_name', 'Yetkili Kişi'),
            new Field('phone', 'Telefon'),
            new Field('email', 'E-posta'),
            new Field('address', 'Adres'),
        ];
    }
}
