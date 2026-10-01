<?php

namespace App\Notifications;

use App\Enums\Portal;
use App\Models\FirmDocument;

class DocumentExpiring extends HrdNotification
{
    public function __construct(public readonly FirmDocument $document, public readonly bool $expired) {}

    public function title(): string
    {
        return $this->expired
            ? "Belgenin süresi doldu: {$this->document->title}"
            : "Belgenin süresi doluyor: {$this->document->title}";
    }

    public function body(): string
    {
        return "{$this->document->firm->name} · {$this->document->type->label()} · son geçerlilik {$this->document->valid_until?->format('d.m.Y')}. Güncel belgeyi yükleyin.";
    }

    public function url(object $notifiable): string
    {
        return Portal::Panel->url('belgeler');
    }

    public function level(): string
    {
        return $this->expired ? 'danger' : 'warning';
    }
}
