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

    /**
     * Two-letter avatar initials for an organization name, skipping legal suffixes.
     *
     * "Oigo Yazılım A.Ş." → "OY", "Oigo Gıda San. ve Tic. A.Ş." → "OG", "Beta" → "BE".
     */
    public static function initials(?string $name): string
    {
        $words = array_values(array_filter(
            preg_split('/\s+/u', trim((string) $name)) ?: [],
            fn (string $word) => ! preg_match('/^(a\.?ş\.?|ltd\.?|şti\.?|san\.?|tic\.?|ve|ltd\.?şti\.?|inc\.?|co\.?)$/iu', $word),
        ));

        $initials = match (count($words)) {
            0 => '',
            1 => mb_substr($words[0], 0, 2),
            default => mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1),
        };

        return mb_strtoupper(strtr($initials, ['i' => 'İ', 'ı' => 'I']));
    }
}
