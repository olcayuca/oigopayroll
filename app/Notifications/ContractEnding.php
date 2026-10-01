<?php

namespace App\Notifications;

use App\Enums\Portal;
use App\Models\FirmContract;

class ContractEnding extends HrdNotification
{
    public function __construct(public readonly FirmContract $contract) {}

    public function title(): string
    {
        return "Sözleşme bitiyor: {$this->contract->firm->name}";
    }

    public function body(): string
    {
        $deadline = $this->contract->noticeDeadline();

        return "{$this->contract->contract_no} {$this->contract->ends_on?->format('d.m.Y')} tarihinde bitiyor"
            .($this->contract->auto_renew ? ' (otomatik yenilenecek)' : '')
            .($deadline ? ", son bildirim tarihi {$deadline->format('d.m.Y')}." : '.');
    }

    public function url(object $notifiable): string
    {
        return Portal::Admin->url('sozlesmeler?sekme=bitiyor');
    }

    public function level(): string
    {
        return 'warning';
    }
}
