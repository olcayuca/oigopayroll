<?php

namespace App\Enums;

/**
 * Bulk import lifecycle: nothing is persisted until the preview is confirmed.
 */
enum ImportStatus: string
{
    use HasLabels;

    case Validated = 'validated';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Get the Turkish display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Validated => 'Kontrol Edildi',
            self::Completed => 'Tamamlandı',
            self::Cancelled => 'İptal Edildi',
        };
    }
}
