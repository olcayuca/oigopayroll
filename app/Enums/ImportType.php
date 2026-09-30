<?php

namespace App\Enums;

/**
 * Bulk Excel import kinds.
 */
enum ImportType: string
{
    use HasLabels;

    case Company = 'company';
    case Workplace = 'workplace';

    /**
     * Get the Turkish display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Company => 'Şirket',
            self::Workplace => 'İşyeri',
        };
    }
}
