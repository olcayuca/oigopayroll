<?php

namespace App\Enums;

/**
 * İSG tehlike sınıfı.
 */
enum HazardClass: string
{
    use HasLabels;

    case Low = 'az_tehlikeli';
    case Medium = 'tehlikeli';
    case High = 'cok_tehlikeli';

    /**
     * Get the Turkish display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Low => 'Az Tehlikeli',
            self::Medium => 'Tehlikeli',
            self::High => 'Çok Tehlikeli',
        };
    }
}
