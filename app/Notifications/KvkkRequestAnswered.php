<?php

namespace App\Notifications;

use App\Enums\Portal;
use App\Enums\UserType;
use App\Models\KvkkRequest;
use App\Models\User;

class KvkkRequestAnswered extends HrdNotification
{
    public function __construct(public readonly KvkkRequest $request) {}

    public function title(): string
    {
        return "KVKK başvurunuz #{$this->request->id}: {$this->request->status->label()}";
    }

    public function body(): string
    {
        return 'Başvurunuza yanıt verildi. Yanıtı Ayarlar → KVKK → Başvurularım bölümünde görebilirsiniz.';
    }

    public function url(object $notifiable): string
    {
        $portal = $notifiable instanceof User && $notifiable->type === UserType::SuperAdmin ? Portal::Admin : Portal::Panel;

        return $portal->url('settings/kvkk?sekme=basvurular');
    }
}
