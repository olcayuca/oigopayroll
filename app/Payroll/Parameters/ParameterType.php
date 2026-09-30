<?php

namespace App\Payroll\Parameters;

/**
 * How a legal parameter's value is stored and validated.
 *
 * Numbers are stored as decimal strings ("33030.00", "12.00") to avoid float rounding.
 */
enum ParameterType: string
{
    /** Turkish lira amount. */
    case Money = 'money';

    /** Percentage, e.g. "12.00" means 12 %. */
    case Percent = 'percent';

    /** Percentage points, e.g. the treasury premium discount ("2.00" = 2 points). */
    case Points = 'points';

    /**
     * Progressive tax brackets: [{"up_to": "190000.00", "rate": "15.00"}, ..., {"up_to": null, "rate": "40.00"}].
     * "up_to" is the cumulative annual tax base limit; the last bracket has no limit.
     */
    case Brackets = 'brackets';

    public function unit(): string
    {
        return match ($this) {
            self::Money => 'TL',
            self::Percent => '%',
            self::Points => 'puan',
            self::Brackets => '',
        };
    }
}
