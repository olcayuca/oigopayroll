<?php

namespace App\Notifications;

use App\Models\FirmContract;
use App\Models\FirmDocument;
use Illuminate\Support\Facades\Notification;

/**
 * Daily reminders (scheduled): documents expiring / expired, contracts ending.
 * Each state is announced once; changing the date re-arms the reminder.
 */
class SendReminders
{
    /**
     * @return array{documents: int, contracts: int}
     */
    public function run(): array
    {
        return ['documents' => $this->documents(), 'contracts' => $this->contracts()];
    }

    private function documents(): int
    {
        $sent = 0;

        FirmDocument::query()->with('firm')->needsAttention()->each(function (FirmDocument $document) use (&$sent) {
            $state = $document->validity();

            if (! in_array($state, [FirmDocument::EXPIRING, FirmDocument::EXPIRED], true) || $document->expiry_notice === $state) {
                return;
            }

            Notification::send(Recipients::firm($document->firm), new DocumentExpiring($document, $state === FirmDocument::EXPIRED));
            $document->forceFill(['expiry_notice' => $state])->save();
            $sent++;
        });

        return $sent;
    }

    private function contracts(): int
    {
        $sent = 0;

        FirmContract::query()->with('firm')->endingSoon()->each(function (FirmContract $contract) use (&$sent) {
            if ($contract->ending_notice_for?->isSameDay($contract->ends_on) ?? false) {
                return;
            }

            Notification::send(Recipients::superAdmins(), new ContractEnding($contract));
            $contract->forceFill(['ending_notice_for' => $contract->ends_on])->save();
            $sent++;
        });

        return $sent;
    }
}
