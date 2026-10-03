<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\UserReminder;

/**
 * Announce personal reminders whose time has come. Runs every minute (scheduler) and on the
 * user's notification bell poll, so reminders arrive even where the scheduler is not running.
 * Each reminder is claimed atomically before sending: it is announced exactly once.
 */
class DeliverDueReminders
{
    public function run(?User $user = null): int
    {
        $sent = 0;

        UserReminder::query()->due()->with(['user', 'firm'])
            ->when($user, fn ($query) => $query->where('user_id', $user?->id))
            ->orderBy('remind_at')->limit(200)
            ->get()
            ->each(function (UserReminder $reminder) use (&$sent) {
                $claimed = UserReminder::query()->whereKey($reminder->id)->whereNull('notified_at')->update(['notified_at' => now()]);

                if ($claimed === 1) {
                    $reminder->user->notify(new PersonalReminder($reminder));
                    $sent++;
                }
            });

        return $sent;
    }
}
