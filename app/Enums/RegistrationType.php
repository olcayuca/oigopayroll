<?php

namespace App\Enums;

/**
 * Tescil tipi.
 */
enum RegistrationType: string
{
    use HasLabels;

    case Natural = 'gercek';
    case Legal = 'tuzel';

    /**
     * Get the Turkish display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Natural => 'Gerçek',
            self::Legal => 'Tüzel',
        };
    }
}
