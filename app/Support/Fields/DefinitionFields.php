<?php

namespace App\Support\Fields;

use App\Enums\DefinitionType;

/**
 * Tanımlar (Panel → Tanımlar) import columns: one row per entry, all types in one sheet.
 */
final class DefinitionFields
{
    /**
     * @return list<Field>
     */
    public static function all(): array
    {
        return [
            new Field('type', 'Tür', required: true, type: Field::SELECT,
                options: fn (): array => array_map(fn (DefinitionType $type) => $type->singular(), DefinitionType::cases()),
                hint: 'Üst Birim, Birim, İş Ailesi, Unvan, Pozisyon, Seviye veya Masraf Grubu.', aliases: ['Tanım Türü']),
            new Field('name', 'Ad', required: true, aliases: ['Tanım Adı']),
            new Field('code', 'Kod', hint: 'Türü içinde benzersiz; boşsa addan üretilir. Eşleşen kod (yoksa ad) güncellenir.', aliases: ['Tanım Kodu']),
            new Field('parent', 'Üst Tanım', hint: 'Birim için üst birim, pozisyon için birim (ad veya kod). Yoksa oluşturulur.'),
            new Field('cost_center', 'Masraf Merkezi Kodu', hint: 'Yalnızca masraf grupları.'),
            new Field('account_code', 'Muhasebe Hesap Kodu', hint: 'Yalnızca masraf grupları.'),
            new Field('is_active', 'Durum', type: Field::SELECT, options: fn (): array => ['Aktif', 'Pasif'], hint: 'Boşsa Aktif.'),
        ];
    }
}
