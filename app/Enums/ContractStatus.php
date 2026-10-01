<?php

namespace App\Enums;

/**
 * Derived from the dates (see FirmContract::status()); not stored.
 */
enum ContractStatus: string
{
    use HasLabels;

    case Upcoming = 'baslamadi';
    case Active = 'aktif';
    case Ending = 'bitiyor';
    case Expired = 'sona_erdi';
    case Terminated = 'feshedildi';

    /**
     * Get the Turkish display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'Başlamadı',
            self::Active => 'Aktif',
            self::Ending => 'Bitişi yaklaşıyor',
            self::Expired => 'Sona erdi',
            self::Terminated => 'Feshedildi',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Upcoming => 'sky',
            self::Active => 'green',
            self::Ending => 'amber',
            self::Expired, self::Terminated => 'zinc',
        };
    }
}
