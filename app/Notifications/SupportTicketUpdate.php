<?php

namespace App\Notifications;

use App\Enums\Portal;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Notifications\AnonymousNotifiable;

/**
 * Destek talebi events (new ticket, reply, status change). Users get it in the bell and by e-mail;
 * the support mailbox (Sistem Ayarları → destek e-postası) by e-mail only.
 * Super admins are sent to the admin portal, everyone else to the panel.
 */
class SupportTicketUpdate extends HrdNotification
{
    public function __construct(
        public readonly SupportTicket $ticket,
        private readonly string $title,
        private readonly string $body,
        private readonly string $level = 'info',
    ) {}

    public function title(): string
    {
        return $this->title;
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
        $toAdmin = $notifiable instanceof AnonymousNotifiable || ($notifiable instanceof User && $notifiable->isSuperAdmin());

        return $toAdmin
            ? Portal::Admin->url('destek-talepleri/'.$this->ticket->id)
            : Portal::Panel->url('destek-talepleri/'.$this->ticket->id);
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof AnonymousNotifiable) {
            return self::mailEnabled() ? ['mail'] : [];
        }

        return parent::via($notifiable);
    }
}
