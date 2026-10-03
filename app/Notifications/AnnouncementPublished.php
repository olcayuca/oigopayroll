<?php

namespace App\Notifications;

use App\Enums\Portal;
use App\Models\Announcement;
use Illuminate\Support\Str;

class AnnouncementPublished extends HrdNotification
{
    public function __construct(public readonly Announcement $announcement) {}

    /**
     * Critical announcements (bakım, kesinti) reach everyone; the rest follow the user's choice.
     */
    public function category(): NotificationCategory
    {
        return $this->announcement->level === 'critical' ? NotificationCategory::System : NotificationCategory::Announcements;
    }

    public function title(): string
    {
        return "Duyuru · {$this->announcement->category}: {$this->announcement->title}";
    }

    public function body(): string
    {
        return Str::limit(trim(strip_tags(Str::markdown($this->announcement->body))), 300);
    }

    public function url(object $notifiable): string
    {
        return Portal::Panel->url('duyurular?duyuru='.$this->announcement->id);
    }

    public function level(): string
    {
        return ['warning' => 'warning', 'critical' => 'danger'][$this->announcement->level] ?? 'info';
    }
}
