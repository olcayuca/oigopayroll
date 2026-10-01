<?php

namespace App\Enums;

enum ContractFeeType: string
{
    use HasLabels;

    case MonthlyFixed = 'aylik_sabit';
    case MonthlyPerEmployee = 'aylik_calisan_basi';
    case Yearly = 'yillik';
    case OneTime = 'tek_seferlik';

    /**
     * Get the Turkish display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::MonthlyFixed => 'Aylık sabit',
            self::MonthlyPerEmployee => 'Aylık, çalışan başına',
            self::Yearly => 'Yıllık',
            self::OneTime => 'Tek seferlik',
        };
    }
}
