<?php

namespace App\Notifications;

use App\Enums\Portal;
use App\Models\UserReminder;

class PersonalReminder extends HrdNotification
{
    public function __construct(public readonly UserReminder $reminder) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Reminders;
    }

    public function title(): string
    {
        return "Hatırlatma: {$this->reminder->title}";
    }

    public function body(): string
    {
        $context = collect([$this->reminder->firm?->name, $this->reminder->remind_at->format('d.m.Y H:i')])->filter()->implode(' · ');

        return filled($this->reminder->note) ? "{$context} · {$this->reminder->note}" : $context;
    }

    public function url(object $notifiable): string
    {
        return Portal::Panel->url();
    }

    public function level(): string
    {
        return 'warning';
    }
}
