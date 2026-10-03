<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Support\Facades\Notification;

/**
 * Announcements marked "bildirim gönder" are announced once, when they go live: right after saving,
 * or by the scheduler (every minute) for those planned for later.
 */
class DeliverAnnouncements
{
    public function run(): int
    {
        $sent = 0;

        Announcement::query()->live()->where('notify', true)->whereNull('notified_at')->get()
            ->each(function (Announcement $announcement) use (&$sent) {
                // Claim first: a parallel run cannot send it twice.
                if (Announcement::query()->whereKey($announcement->id)->whereNull('notified_at')->update(['notified_at' => now()]) !== 1) {
                    return;
                }

                Notification::send($announcement->recipients(), new AnnouncementPublished($announcement));
                $sent++;
            });

        return $sent;
    }
}
