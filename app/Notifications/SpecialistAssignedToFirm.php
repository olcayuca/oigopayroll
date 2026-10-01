<?php

namespace App\Notifications;

use App\Enums\Portal;
use App\Models\Firm;

class SpecialistAssignedToFirm extends HrdNotification
{
    public function __construct(public readonly Firm $firm) {}

    public function title(): string
    {
        return "{$this->firm->name} firmasının sorumlu uzmanı oldunuz";
    }

    public function body(): string
    {
        return 'Firma, panelde firma seçicisinde görünür; bordro işlemlerini bu firmada yürütebilirsiniz.';
    }

    public function url(object $notifiable): string
    {
        return Portal::Panel->url('/');
    }
}
