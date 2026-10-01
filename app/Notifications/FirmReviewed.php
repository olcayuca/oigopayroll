<?php

namespace App\Notifications;

use App\Enums\Portal;
use App\Models\Firm;

class FirmReviewed extends HrdNotification
{
    public function __construct(public readonly Firm $firm, public readonly bool $approved) {}

    public function title(): string
    {
        return $this->approved ? "{$this->firm->name} onaylandı" : "{$this->firm->name} başvurusu reddedildi";
    }

    public function body(): string
    {
        return $this->approved
            ? 'Firmanız HRD tarafından onaylandı. Şirket ve işyeri bilgilerinizi girmeye başlayabilirsiniz.'
            : 'Gerekçe: '.($this->firm->rejection_reason ?? '—');
    }

    public function url(object $notifiable): string
    {
        return Portal::Panel->url('/');
    }

    public function level(): string
    {
        return $this->approved ? 'success' : 'danger';
    }
}
