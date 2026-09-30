<?php

namespace App\Enums;

/**
 * İşyeri tipi.
 */
enum WorkplaceType: string
{
    use HasLabels;

    case Headquarters = 'merkez';
    case Branch = 'sube';
    case Subcontractor = 'taseron';

    /**
     * Get the Turkish display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Headquarters => 'Merkez İşyeri',
            self::Branch => 'Şube İşyeri',
            self::Subcontractor => 'Taşeron',
        };
    }
}
