<?php

namespace App\Enums;

enum TicketPriority: string
{
    use HasLabels;

    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Düşük',
            self::Normal => 'Normal',
            self::High => 'Yüksek',
            self::Critical => 'Kritik',
        };
    }

    /**
     * Badge color (Flux names; x-panel.badge maps them).
     */
    public function color(): string
    {
        return match ($this) {
            self::Low => 'zinc',
            self::Normal => 'blue',
            self::High => 'amber',
            self::Critical => 'rose',
        };
    }
}
