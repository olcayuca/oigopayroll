<?php

namespace App\Enums;

/**
 * Who created the firm.
 */
enum FirmSource: string
{
    use HasLabels;

    case Client = 'client';
    case Hrd = 'hrd';

    /**
     * Get the Turkish display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Client => 'Müşteri',
            self::Hrd => 'HRD',
        };
    }
}
