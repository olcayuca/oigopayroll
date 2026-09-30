<?php

namespace App\Enums;

enum KvkkRequestStatus: string
{
    use HasLabels;

    case Open = 'acik';
    case InProgress = 'inceleniyor';
    case Completed = 'tamamlandi';
    case Rejected = 'reddedildi';

    /**
     * Get the Turkish display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Açık',
            self::InProgress => 'İnceleniyor',
            self::Completed => 'Tamamlandı',
            self::Rejected => 'Reddedildi',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'amber',
            self::InProgress => 'sky',
            self::Completed => 'green',
            self::Rejected => 'zinc',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Completed, self::Rejected], true);
    }
}
