<?php

namespace App\Rules;

use App\Support\TurkishIdentifiers;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Vergi numarası: a valid 10 digit VKN, or (for sole proprietors) an 11 digit TCKN.
 */
class TaxNumber implements ValidationRule
{
    public function __construct(private bool $allowTckn = false)
    {
        //
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = is_scalar($value) ? (string) $value : '';

        if (TurkishIdentifiers::isValidVkn($value)) {
            return;
        }

        if ($this->allowTckn && TurkishIdentifiers::isValidTckn($value)) {
            return;
        }

        $fail($this->allowTckn
            ? ':attribute geçerli bir vergi numarası (10 hane) veya T.C. kimlik numarası (11 hane) olmalıdır.'
            : ':attribute geçerli bir 10 haneli vergi numarası olmalıdır.');
    }
}
