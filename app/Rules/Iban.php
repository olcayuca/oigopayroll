<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Turkish IBAN: TR + 24 digits, ISO 13616 mod-97 check. Expects spaces already removed.
 */
class Iban implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $iban = is_scalar($value) ? strtoupper((string) $value) : '';

        if (! preg_match('/^TR\d{24}$/', $iban) || ! self::checksumIsValid($iban)) {
            $fail(':attribute geçerli bir TR IBAN olmalıdır (TR + 24 hane).');
        }
    }

    public static function checksumIsValid(string $iban): bool
    {
        $rearranged = substr($iban, 4).substr($iban, 0, 4);
        $numeric = preg_replace_callback('/[A-Z]/', fn ($letter) => (string) (ord($letter[0]) - 55), $rearranged) ?? '';

        $remainder = 0;
        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = (int) ($remainder.$chunk) % 97;
        }

        return $remainder === 1;
    }

    /**
     * Build a valid TR IBAN from 22 digits (5 bank code + 1 reserve + 16 account), for tests and seeders.
     */
    public static function make(string $bban): string
    {
        $numeric = $bban.'2927'.'00'; // "TR" = 29 27, check digits 00
        $remainder = 0;
        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = (int) ($remainder.$chunk) % 97;
        }

        return 'TR'.str_pad((string) (98 - $remainder), 2, '0', STR_PAD_LEFT).$bban;
    }
}
