<?php

namespace App\Enums;

/**
 * Destek talebi durumu. "Açık" waits for HRD, "Yanıt bekleniyor" waits for the customer.
 */
enum TicketStatus: string
{
    use HasLabels;

    case Open = 'open';
    case InProgress = 'in_progress';
    case AwaitingCustomer = 'awaiting_customer';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Açık',
            self::InProgress => 'İnceleniyor',
            self::AwaitingCustomer => 'Yanıt bekleniyor',
            self::Resolved => 'Çözüldü',
            self::Closed => 'Kapalı',
        };
    }

    /**
     * Badge color (Flux names; x-panel.badge maps them).
     */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'blue',
            self::InProgress => 'amber',
            self::AwaitingCustomer => 'indigo',
            self::Resolved => 'green',
            self::Closed => 'zinc',
        };
    }

    /**
     * Still being worked on ("Açık talepler" tab); the rest is history ("Geçmiş").
     */
    public function isActive(): bool
    {
        return in_array($this, self::active(), true);
    }

    /**
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Open, self::InProgress, self::AwaitingCustomer];
    }
}
