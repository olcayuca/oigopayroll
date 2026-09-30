<?php

namespace App\Enums;

/**
 * İşyeri türü.
 */
enum WorkplaceKind: string
{
    use HasLabels;

    case Normal = 'normal';
    case RnD = 'arge';
    case Technopark = 'teknopark';

    /**
     * Get the Turkish display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::RnD => 'Ar-Ge',
            self::Technopark => 'Teknopark',
        };
    }
}
