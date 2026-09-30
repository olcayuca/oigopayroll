<?php

namespace App\Enums;

/**
 * Lifecycle of a client firm (müşteri firma).
 */
enum FirmStatus: string
{
    use HasLabels;

    case Pending = 'pending';
    case Active = 'active';
    case Rejected = 'rejected';
    case Passive = 'passive';

    /**
     * Get the Turkish display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Onay Bekliyor',
            self::Active => 'Aktif',
            self::Rejected => 'Reddedildi',
            self::Passive => 'Pasif',
        };
    }

    /**
     * Get the Flux badge color.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Active => 'green',
            self::Rejected => 'red',
            self::Passive => 'zinc',
        };
    }
}
