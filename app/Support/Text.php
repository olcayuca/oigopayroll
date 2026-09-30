<?php

namespace App\Support;

final class Text
{
    /**
     * Build a comparison key: Turkish-aware lowercase, diacritics folded, only [a-z0-9].
     *
     * "İş Yeri Numarası *", "İşyeri numarası" and "ISYERI NUMARASI" all become "isyerinumarasi".
     */
    public static function key(?string $value): string
    {
        $value = mb_strtolower(strtr((string) $value, ['I' => 'ı', 'İ' => 'i']));
        $value = strtr($value, ['ı' => 'i', 'ş' => 's', 'ğ' => 'g', 'ü' => 'u', 'ö' => 'o', 'ç' => 'c', 'â' => 'a', 'î' => 'i', 'û' => 'u']);

        return (string) preg_replace('/[^a-z0-9]/', '', $value);
    }
}
