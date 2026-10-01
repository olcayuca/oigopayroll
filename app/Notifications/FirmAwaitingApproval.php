<?php

namespace App\Notifications;

use App\Enums\Portal;
use App\Models\Firm;

class FirmAwaitingApproval extends HrdNotification
{
    public function __construct(public readonly Firm $firm) {}

    public function title(): string
    {
        return "Onay bekleyen firma: {$this->firm->name}";
    }

    public function body(): string
    {
        return $this->firm->parent_firm_id
            ? "{$this->firm->parent?->name} firmasının açtığı alt firma onay bekliyor."
            : 'Yeni firma başvurusu onay bekliyor.';
    }

    public function url(object $notifiable): string
    {
        return Portal::Admin->url("firmalar/{$this->firm->id}");
    }

    public function level(): string
    {
        return 'warning';
    }
}
