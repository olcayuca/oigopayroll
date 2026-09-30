<?php

namespace App\Rules;

use App\Support\TurkishIdentifiers;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Tckn implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! TurkishIdentifiers::isValidTckn(is_scalar($value) ? (string) $value : '')) {
            $fail(':attribute geçerli bir 11 haneli T.C. kimlik numarası olmalıdır.');
        }
    }
}
