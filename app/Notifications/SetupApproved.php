<?php

namespace App\Notifications;

use App\Enums\Portal;
use App\Models\Firm;
use App\Models\User;

class SetupApproved extends HrdNotification
{
    public function __construct(public readonly Firm $firm, public readonly User $approver) {}

    public function title(): string
    {
        return "Kurulum onaylandı: {$this->firm->name}";
    }

    public function body(): string
    {
        return "{$this->approver->name} şirket, işyeri, tanım ve personel bilgilerini kontrol edip onayladı. Bundan sonraki değişiklikleri bordro uzmanınıza bildirin.";
    }

    public function url(object $notifiable): string
    {
        return Portal::Panel->url('kurulum');
    }

    public function level(): string
    {
        return 'success';
    }
}
