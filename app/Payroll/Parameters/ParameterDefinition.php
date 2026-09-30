<?php

namespace App\Payroll\Parameters;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ParameterDefinition
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $group,
        public readonly ParameterType $type,
        public readonly string $description = '',
    ) {
        //
    }

    /**
     * Validate and normalise a value (decimal strings, sorted brackets).
     *
     * @return string|list<array{up_to: string|null, rate: string}>
     *
     * @throws ValidationException
     */
    public function normalize(mixed $value): string|array
    {
        if ($this->type === ParameterType::Brackets) {
            return $this->normalizeBrackets($value);
        }

        $max = $this->type === ParameterType::Money ? 1_000_000_000 : 100;

        Validator::make(['value' => $value], ['value' => ['required', 'numeric', 'min:0', 'max:'.$max]], [], ['value' => $this->label])->validate();

        // Rates need more precision than money (e.g. stamp tax 0.759 %).
        return number_format((float) $value, $this->type === ParameterType::Percent ? 4 : 2, '.', '');
    }

    /**
     * Human readable value, e.g. "33.030,00 TL", "%12,00".
     */
    public function format(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        return match ($this->type) {
            ParameterType::Money => number_format((float) $value, 2, ',', '.').' TL',
            ParameterType::Percent => '%'.rtrim(rtrim(number_format((float) $value, 4, ',', '.'), '0'), ','),
            ParameterType::Points => number_format((float) $value, 2, ',', '.').' puan',
            ParameterType::Brackets => count(is_array($value) ? $value : []).' dilim',
        };
    }

    /**
     * @return list<array{up_to: string|null, rate: string}>
     */
    private function normalizeBrackets(mixed $value): array
    {
        if (! is_array($value) || $value === []) {
            throw ValidationException::withMessages(['value' => 'En az bir dilim girilmelidir.']);
        }

        $brackets = [];
        $previous = 0.0;
        $count = count($value);

        foreach (array_values($value) as $index => $row) {
            $number = $index + 1;
            $upTo = is_array($row) ? ($row['up_to'] ?? null) : null;
            $rate = is_array($row) ? ($row['rate'] ?? null) : null;
            $isLast = $index === $count - 1;

            if (! is_numeric($rate) || (float) $rate < 0 || (float) $rate > 100) {
                throw ValidationException::withMessages(['value' => "{$number}. dilimin oranı 0–100 arasında olmalıdır."]);
            }

            if ($isLast) {
                if ($upTo !== null && $upTo !== '') {
                    throw ValidationException::withMessages(['value' => 'Son dilimin üst sınırı boş (sınırsız) olmalıdır.']);
                }
                $upTo = null;
            } elseif (! is_numeric($upTo) || (float) $upTo <= $previous) {
                throw ValidationException::withMessages(['value' => "{$number}. dilimin üst sınırı bir önceki dilimden büyük olmalıdır."]);
            } else {
                $previous = (float) $upTo;
                $upTo = number_format((float) $upTo, 2, '.', '');
            }

            $brackets[] = ['up_to' => $upTo, 'rate' => number_format((float) $rate, 4, '.', '')];
        }

        return $brackets;
    }
}
