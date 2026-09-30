<?php

namespace App\Enums;

/**
 * The level an access grant applies to. A grant covers everything beneath it.
 */
enum ScopeType: string
{
    use HasLabels;

    case Firm = 'firm';
    case Company = 'company';
    case Workplace = 'workplace';

    /**
     * Get the Turkish display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Firm => 'Firma',
            self::Company => 'Şirket',
            self::Workplace => 'İşyeri',
        };
    }
}
