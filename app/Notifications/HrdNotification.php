<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base for system notifications: always stored in-app; e-mailed as well once a real
 * mailer is configured (MAIL_MAILER other than log / array).
 */
abstract class HrdNotification extends Notification
{
    use Queueable;

    abstract public function title(): string;

    abstract public function body(): string;

    /**
     * Where the notification leads (absolute URL on one of the portals).
     */
    abstract public function url(object $notifiable): string;

    /**
     * info | success | warning | danger
     */
    public function level(): string
    {
        return 'info';
    }

    /**
     * Group the user can switch on or off (Panel → Ayarlar → Bildirimler).
     */
    public function category(): NotificationCategory
    {
        return NotificationCategory::System;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = self::mailEnabled() ? ['database', 'mail'] : ['database'];

        if (! $notifiable instanceof User) {
            return $channels;
        }

        return array_values(array_filter($channels, fn (string $channel) => $notifiable->wantsNotification($this->category(), $channel === 'database' ? 'panel' : 'mail')));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->url($notifiable),
            'level' => $this->level(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->line($this->body())
            ->action('Görüntüle', $this->url($notifiable));
    }

    public static function mailEnabled(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array', null], true);
    }
}
