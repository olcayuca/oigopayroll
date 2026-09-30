<?php

namespace App\Support;

/**
 * Checksum helpers for Turkish identifiers (VKN and T.C. Kimlik No).
 */
final class TurkishIdentifiers
{
    /**
     * Validate a 10 digit tax number (Vergi Kimlik Numarası).
     */
    public static function isValidVkn(string $value): bool
    {
        if (preg_match('/^\d{10}$/', $value) !== 1) {
            return false;
        }

        return self::vknCheckDigit(substr($value, 0, 9)) === (int) $value[9];
    }

    /**
     * Validate an 11 digit T.C. Kimlik Numarası.
     */
    public static function isValidTckn(string $value): bool
    {
        if (preg_match('/^[1-9]\d{10}$/', $value) !== 1) {
            return false;
        }

        return substr($value, 9, 2) === self::tcknCheckDigits(substr($value, 0, 9));
    }

    /**
     * Build a valid VKN from its first 9 digits.
     */
    public static function makeVkn(string $firstNine): string
    {
        return $firstNine.self::vknCheckDigit($firstNine);
    }

    /**
     * Build a valid TCKN from its first 9 digits.
     */
    public static function makeTckn(string $firstNine): string
    {
        return $firstNine.self::tcknCheckDigits($firstNine);
    }

    private static function vknCheckDigit(string $firstNine): int
    {
        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $tmp = ((int) $firstNine[$i] + 9 - $i) % 10;
            $part = ($tmp * (2 ** (9 - $i))) % 9;

            if ($tmp !== 0 && $part === 0) {
                $part = 9;
            }

            $sum += $part;
        }

        return (10 - ($sum % 10)) % 10;
    }

    private static function tcknCheckDigits(string $firstNine): string
    {
        $digits = array_map('intval', str_split($firstNine));

        $odd = $digits[0] + $digits[2] + $digits[4] + $digits[6] + $digits[8];
        $even = $digits[1] + $digits[3] + $digits[5] + $digits[7];

        $tenth = ((($odd * 7) - $even) % 10 + 10) % 10;
        $eleventh = (array_sum($digits) + $tenth) % 10;

        return $tenth.$eleventh;
    }
}
