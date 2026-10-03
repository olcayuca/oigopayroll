<?php

namespace App\Support;

/**
 * Choice lists of the personnel record, as in the customer setup file (Personel Bilgileri sheet).
 * The label itself is stored; match() accepts any casing / Turkish characters / missing spaces.
 */
final class EmployeeOptions
{
    public const GENDER = ['Kadın', 'Erkek'];

    public const MARITAL_STATUS = ['Bekar', 'Evli'];

    public const EDUCATION = ['Doktora', 'Yüksek Lisans', 'Lisans', 'Ön Lisans', 'Lise', 'Orta Okul', 'İlkokul', 'Okur Yazar', 'Okur Yazar Değil'];

    public const MILITARY = ['Yapıldı', 'Yapılmadı', 'Tecilli', 'Muaf'];

    public const COLLAR = ['Beyaz Yaka', 'Mavi Yaka'];

    public const DUTY_TYPE = ['Kadrolu', 'Stajyer', 'Outsource'];

    public const INSURANCE_BRANCH = ['Tüm Sigorta Kolları (Zorunlu)', 'Sosyal Güvenlik Destek Primi', 'Çırak', 'Stajyer'];

    public const SGK_STATUS = ['Normal', 'Emekli', 'Kapsam Dışı'];

    /** "Çalışan Tipi" column: the employment contract's duration. */
    public const EMPLOYMENT_TYPE = ['Belirsiz Süreli', 'Belirli Süreli', 'Kısmi Süreli', 'Çağrı Üzerine'];

    public const DUTY_CODE = ['İşçi', 'İşveren veya Vekili', 'Çıraklar ve Stajer Öğrenciler', 'Diğerleri'];

    public const WAGE_PERIOD = ['Aylık', 'Haftalık', 'Günlük', 'Saatlik'];

    public const CURRENCY = ['TRY', 'EUR', 'USD', 'GBP'];

    public const WAGE_TYPE = ['Net', 'Brüt'];

    public const RND_RATE = ['0', '%80', '%90', '%95', '%100 (4691)'];

    public const DISABILITY_DEGREE = ['1. Derece', '2. Derece', '3. Derece'];

    public const WORK_MODEL = ['Ofiste Çalışma', 'Evde Çalışma', 'Hibrit'];

    public const CONTRACT_TYPE = ['Tam Zamanlı', 'Kısmi Süreli'];

    public const WEEKLY_REST = ['Pazar', 'Cumartesi & Pazar', 'Sabit Değil / Haftanın Bir Günü'];

    public const MONTHS = [1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

    /**
     * Spreadsheet spellings that differ from the labels: Text::key(alias) => label.
     */
    private const ALIASES = [
        'okuyazardegil' => 'Okur Yazar Değil',
        'outsources' => 'Outsource',
        'euro' => 'EUR',
        'gpb' => 'GBP',
        'tl' => 'TRY',
        'derece1' => '1. Derece',
        'derece2' => '2. Derece',
        'derece3' => '3. Derece',
        '80' => '%80',
        '90' => '%90',
        '95' => '%95',
        '100' => '%100 (4691)',
        '1004691' => '%100 (4691)',
        '0' => '0',
        'sabitdegilhaftaninbirgunu' => 'Sabit Değil / Haftanın Bir Günü',
    ];

    /**
     * The label of $options matching $value, or the value unchanged (validation then rejects it).
     *
     * @param  list<string>  $options
     */
    public static function match(array $options, mixed $value): mixed
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return $value;
        }

        $key = Text::key((string) $value);

        foreach ($options as $option) {
            if (Text::key($option) === $key) {
                return $option;
            }
        }

        $alias = self::ALIASES[$key] ?? null;

        return $alias !== null && in_array($alias, $options, true) ? $alias : $value;
    }

    /**
     * Month number from "Ocak", "1", "01".
     */
    public static function month(mixed $value): mixed
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        $key = Text::key(is_scalar($value) ? (string) $value : '');

        foreach (self::MONTHS as $number => $name) {
            if (Text::key($name) === $key) {
                return $number;
            }
        }

        return $value;
    }
}
