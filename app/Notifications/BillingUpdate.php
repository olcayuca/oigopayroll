<?php

namespace App\Notifications;

use App\Enums\Portal;
use App\Models\Firm;

/**
 * Abonelik: new invoice, payment received, payment failed. Failed payments are always delivered.
 */
class BillingUpdate extends HrdNotification
{
    public function __construct(
        public readonly Firm $firm,
        private readonly string $title,
        private readonly string $body,
        private readonly string $level = 'info',
        private readonly bool $critical = false,
    ) {}

    public function category(): NotificationCategory
    {
        return $this->critical ? NotificationCategory::System : NotificationCategory::Billing;
    }

    public function title(): string
    {
        return "{$this->title} · {$this->firm->name}";
    }

    public function body(): string
    {
        return $this->body;
    }

    public function level(): string
    {
        return $this->level;
    }

    public function url(object $notifiable): string
    {
        return Portal::Panel->url('abonelik');
    }
}
